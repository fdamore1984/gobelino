<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\EnrollmentToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints called by the agent APK. No web session/CSRF: devices
 * authenticate with either the one-time enrollment token (only for
 * /enroll) or their permanent device_token (for every other call).
 */
class DeviceAgentController extends Controller
{
    /**
     * First call the agent makes right after being set as Device
     * Owner. Trades the one-time enrollment token for a permanent
     * device_token and registers the device.
     */
    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_token' => ['required', 'string'],
            'model' => ['nullable', 'string'],
            'manufacturer' => ['nullable', 'string'],
            'android_version' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string'],
            'imei' => ['nullable', 'string'],
        ]);

        $enrollmentToken = EnrollmentToken::where('token', $data['enrollment_token'])
            ->where('platform', 'android')
            ->first();

        if (! $enrollmentToken) {
            return response()->json(['error' => 'invalid_token'], 404);
        }

        if ($enrollmentToken->used) {
            return response()->json(['error' => 'token_already_used'], 409);
        }

        if ($enrollmentToken->expires_at && $enrollmentToken->expires_at->isPast()) {
            return response()->json(['error' => 'token_expired'], 410);
        }

        $device = Device::create([
            'company_id' => $enrollmentToken->company_id,
            'added_by' => $enrollmentToken->created_by,
            'platform' => 'android',
            'device_token' => Device::generateDeviceToken(),
            'name' => trim(($data['manufacturer'] ?? '').' '.($data['model'] ?? '')) ?: 'Android device',
            'model' => $data['model'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'android_version' => $data['android_version'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'imei' => $data['imei'] ?? null,
            'status' => 'active',
            'is_device_owner' => true,
            'last_poll_at' => now(),
        ]);

        $enrollmentToken->markUsed();

        return response()->json([
            'device_id' => $device->id,
            'device_token' => $device->device_token,
            'poll_interval_seconds' => $device->poll_interval_seconds,
        ], 201);
    }

    /**
     * Heartbeat + command exchange, called periodically (WorkManager)
     * by every enrolled device. In one round-trip the agent: (1)
     * reports its current status and the results of previously sent
     * commands, (2) receives newly queued commands to execute.
     */
    public function poll(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);

        if (! $device) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'android_version' => ['nullable', 'string'],
            'agent_app_version' => ['nullable', 'string'],
            'command_results' => ['array'],
            'command_results.*.id' => ['required', 'integer'],
            'command_results.*.status' => ['required', 'string', 'in:acked,failed'],
            'command_results.*.result' => ['nullable', 'string'],
        ]);

        foreach ($data['command_results'] ?? [] as $result) {
            DeviceCommand::where('id', $result['id'])
                ->where('device_id', $device->id)
                ->whereIn('status', [DeviceCommand::STATUS_SENT, DeviceCommand::STATUS_PENDING])
                ->update([
                    'status' => $result['status'],
                    'result' => $result['result'] ?? null,
                    'acked_at' => now(),
                ]);
        }

        $device->update(array_filter([
            'battery_level' => $data['battery_level'] ?? null,
            'android_version' => $data['android_version'] ?? null,
            'agent_app_version' => $data['agent_app_version'] ?? null,
            'last_poll_at' => now(),
        ], fn ($value) => $value !== null));

        $pendingCommands = $device->commands()
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        $pendingCommands->each(fn (DeviceCommand $command) => $command->update([
            'status' => DeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]));

        return response()->json([
            'poll_interval_seconds' => $device->poll_interval_seconds,
            'kiosk_enabled' => $device->kiosk_enabled,
            'kiosk_allowed_packages' => $device->kiosk_allowed_packages ?? [],
            'commands' => $pendingCommands->map(fn (DeviceCommand $command) => [
                'id' => $command->id,
                'type' => $command->type,
                'payload' => $command->payload,
            ]),
        ]);
    }

    protected function authenticateDevice(Request $request): ?Device
    {
        $token = $request->header('X-Device-Token') ?? $request->input('device_token');

        if (! $token) {
            return null;
        }

        return Device::where('device_token', $token)->first();
    }
}
