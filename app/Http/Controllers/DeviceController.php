<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\EnrollmentToken;
use App\Services\AndroidAgentService;
use App\Services\IosMdmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $devices = $company->devices()
            ->with(['commands' => fn ($q) => $q->latest()->limit(10)])
            ->latest()
            ->get();

        return view('devices.index', [
            'devices' => $devices,
            'company' => $company,
        ]);
    }

    /**
     * Polled every few seconds by the devices page (see index.blade.php)
     * to refresh device status and the command queue without a full
     * page reload / F5.
     */
    public function status(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $devices = $company->devices()
            ->with(['commands' => fn ($q) => $q->latest()->limit(10)])
            ->latest()
            ->get();

        return response()->json([
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'online' => $device->isAndroid() ? $device->isOnline() : null,
                'kiosk_enabled' => $device->kiosk_enabled,
                'battery_level' => $device->battery_level,
                'serial_number' => $device->serial_number,
                'last_poll_at_human' => $device->last_poll_at?->diffForHumans(),
                'commands' => $device->commands->map(fn (DeviceCommand $command) => [
                    'id' => $command->id,
                    'type' => $command->type,
                    'status' => $command->status,
                    'created_at_human' => $command->created_at->diffForHumans(),
                ]),
            ]),
        ]);
    }

    /**
     * Generates a new enrollment token (QR code) to be scanned
     * by an Android or iOS device during provisioning/reset.
     */
    public function createEnrollment(
        Request $request,
        AndroidAgentService $androidService,
        IosMdmService $iosService
    ): View|RedirectResponse {
        $company = $request->user()->company;

        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        if ($data['platform'] === 'ios') {
            return $this->createIosEnrollment($request, $company, $iosService);
        }

        return $this->createAndroidEnrollment($request, $company, $androidService);
    }

    /**
     * Android no longer goes through the Android Management API: the
     * QR simply carries our own enrollment token and points the
     * device to download+trust our agent APK, which becomes Device
     * Owner via the standard provisioning flow. No Enterprise binding
     * with Google is required.
     */
    protected function createAndroidEnrollment(Request $request, $company, AndroidAgentService $service): View|RedirectResponse
    {
        $enrollmentToken = $service->createEnrollmentToken($company, $request->user()->id);

        return view('devices.enroll', ['enrollmentToken' => $enrollmentToken]);
    }

    protected function createIosEnrollment(Request $request, $company, IosMdmService $service): View|RedirectResponse
    {
        if (! $company->hasApnsConfigured()) {
            return redirect()->route('devices.index')
                ->with('error', 'To add iPhone/iPad devices you must first configure your company\'s APNs push certificate.');
        }

        $result = $service->createEnrollmentToken($company);

        $enrollmentToken = EnrollmentToken::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'platform' => 'ios',
            // We reuse google_name as the local unique identifier of the
            // token (for iOS there's no "Google name" to store here).
            'google_name' => $result['local_token'],
            // For iOS the "QR" encapsulates the profile URL, not JSON.
            'qr_code_json' => $result['qr_payload'],
            'expires_at' => $result['expires_at'],
        ]);

        return view('devices.enroll', ['enrollmentToken' => $enrollmentToken]);
    }

}
