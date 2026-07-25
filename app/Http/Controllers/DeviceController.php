<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\EnrollmentToken;
use App\Services\AndroidEnterpriseService;
use App\Services\IosMdmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $devices = $company->devices()->latest()->get();

        return view('devices.index', [
            'devices' => $devices,
            'company' => $company,
        ]);
    }

    /**
     * Generates a new enrollment token (QR code) to be scanned
     * by an Android or iOS device during provisioning/reset.
     */
    public function createEnrollment(
        Request $request,
        AndroidEnterpriseService $androidService,
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

    protected function createAndroidEnrollment(Request $request, $company, AndroidEnterpriseService $service): View|RedirectResponse
    {
        if (! $company->hasAndroidEnterprise()) {
            return redirect()->route('devices.index')
                ->with('error', 'Connect Android Enterprise first to be able to add devices.');
        }

        $policyName = $company->android_enterprise_name.'/policies/default';
        $googleToken = $service->createEnrollmentToken($company->android_enterprise_name, $policyName);

        $enrollmentToken = EnrollmentToken::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'platform' => 'android',
            'google_name' => $googleToken->getName(),
            'qr_code_json' => $googleToken->getQrCode(),
            'expires_at' => $googleToken->getExpirationTimestamp(),
        ]);

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

    /**
     * Calls the Android Management API to update the local device
     * list with the ones actually enrolled on Google.
     *
     * Note: this only syncs Android devices. For iOS there's no
     * equivalent "listDevices" to query: the state of Apple devices
     * will be updated via MDM check-in (see IosMdmService),
     * not via a periodic sync like this one.
     */
    public function sync(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $company = $request->user()->company;

        if (! $company->hasAndroidEnterprise()) {
            return redirect()->route('devices.index')
                ->with('error', 'Connect Android Enterprise first.');
        }

        $googleDevices = $service->listDevices($company->android_enterprise_name);

        foreach ($googleDevices as $googleDevice) {
            Device::updateOrCreate(
                ['google_device_id' => $googleDevice->getName()],
                [
                    'company_id' => $company->id,
                    'platform' => 'android',
                    'name' => $googleDevice->getHardwareInfo()?->getModel() ?? $googleDevice->getName(),
                    'status' => $googleDevice->getState(),
                    'model' => $googleDevice->getHardwareInfo()?->getModel(),
                    'manufacturer' => $googleDevice->getHardwareInfo()?->getManufacturer(),
                    'android_version' => $googleDevice->getSoftwareInfo()?->getAndroidVersion(),
                    'last_synced_at' => now(),
                ]
            );
        }

        return redirect()->route('devices.index')
            ->with('success', 'Devices synced.');
    }
}
