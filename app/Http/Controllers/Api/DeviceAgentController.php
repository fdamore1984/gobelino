<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\EnrollmentToken;
use App\Services\DeviceWakeupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoints called by the agent APK. No web session/CSRF: devices
 * authenticate with either the one-time enrollment token (only for
 * /enroll) or their permanent device_token (for evaery other call).
 */
class DeviceAgentController extends Controller
{
    public function __construct(protected DeviceWakeupService $wakeup)
    {
    }

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

        // Tutto il check-then-act (token valido? già usato? crea il
        // device? segna il token usato?) deve avvenire come un'unica
        // operazione atomica: con connessioni instabili l'agent può
        // ripetere la stessa richiesta (perché non ha ricevuto la
        // risposta alla precedente, anche se il server l'aveva già
        // gestita), e due tentativi possono arrivare quasi in
        // contemporanea. Senza lock, entrambi possono superare il
        // controllo "used = false" prima che uno dei due lo marchi
        // used, creando due device per lo stesso token.
        $result = DB::transaction(function () use ($data) {
            $enrollmentToken = EnrollmentToken::where('token', $data['enrollment_token'])
                ->where('platform', 'android')
                ->lockForUpdate()
                ->first();

            if (! $enrollmentToken) {
                return ['status' => 404, 'body' => ['error' => 'invalid_token']];
            }

            if ($enrollmentToken->used) {
                // Non necessariamente un errore: potrebbe essere lo
                // stesso agent che ripete l'enroll perché non ha mai
                // ricevuto la risposta alla chiamata precedente, che
                // però lato server era già andata a buon fine. Se
                // troviamo il device creato da questo stesso token,
                // restituiamo di nuovo le sue credenziali invece di un
                // 409 senza via d'uscita (l'agent resterebbe bloccato
                // per sempre con un token ormai inutilizzabile).
                $existingDevice = Device::where('enrollment_token_id', $enrollmentToken->id)->first();

                if ($existingDevice) {
                    return ['status' => 200, 'body' => [
                        'device_id' => $existingDevice->id,
                        'device_token' => $existingDevice->device_token,
                        'poll_interval_seconds' => $existingDevice->poll_interval_seconds,
                    ]];
                }

                return ['status' => 409, 'body' => ['error' => 'token_already_used']];
            }

            if ($enrollmentToken->expires_at && $enrollmentToken->expires_at->isPast()) {
                return ['status' => 410, 'body' => ['error' => 'token_expired']];
            }

            $device = Device::create([
                'company_id' => $enrollmentToken->company_id,
                'added_by' => $enrollmentToken->created_by,
                'enrollment_token_id' => $enrollmentToken->id,
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

            return ['status' => 201, 'body' => [
                'device_id' => $device->id,
                'device_token' => $device->device_token,
                'poll_interval_seconds' => $device->poll_interval_seconds,
            ]];
        });

        return response()->json($result['body'], $result['status']);
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
            'serial_number' => ['nullable', 'string'],
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
            'serial_number' => $data['serial_number'] ?? null,
            'last_poll_at' => now(),
        ], fn ($value) => $value !== null));

        $pendingCommands = $this->fetchAndMarkSent($device);

        if ($pendingCommands->isEmpty()) {
            // Nothing to deliver right now: instead of returning
            // immediately (forcing the agent to wait out a full
            // interval before asking again), hold the request open
            // and give a command created in the meantime a chance to
            // be delivered in THIS response instead of the next one.
            $this->wakeup->wait($device->id);
            $pendingCommands = $this->fetchAndMarkSent($device);
        }

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

    /** Fetches pending commands for a device and marks them as sent. */
    protected function fetchAndMarkSent(Device $device)
    {
        $pendingCommands = $device->commands()
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        $pendingCommands->each(fn (DeviceCommand $command) => $command->update([
            'status' => DeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]));

        return $pendingCommands;
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $device = $this->authenticateDevice($request);

        if (! $device) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        $device->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['status' => 'ok']);
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
