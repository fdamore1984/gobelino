<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\EnrollmentToken;
use App\Services\AndroidEnterpriseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Genera un nuovo token di iscrizione (QR code) da far scansionare
     * a un dispositivo Android in fase di provisioning/reset.
     */
    public function createEnrollment(Request $request, AndroidEnterpriseService $service): View|RedirectResponse
    {
        $company = $request->user()->company;

        if (! $company->hasAndroidEnterprise()) {
            return redirect()->route('devices.index')
                ->with('error', 'Collega prima Android Enterprise per poter aggiungere dispositivi.');
        }

        $policyName = $company->android_enterprise_name.'/policies/default';
        $googleToken = $service->createEnrollmentToken($company->android_enterprise_name, $policyName);

        $enrollmentToken = EnrollmentToken::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'google_name' => $googleToken->getName(),
            'qr_code_json' => $googleToken->getQrCode(),
            'expires_at' => $googleToken->getExpirationTimestamp(),
        ]);

        return view('devices.enroll', ['enrollmentToken' => $enrollmentToken]);
    }

    /**
     * Richiama l'Android Management API per aggiornare l'elenco
     * dispositivi locale con quelli realmente iscritti su Google.
     */
    public function sync(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $company = $request->user()->company;

        if (! $company->hasAndroidEnterprise()) {
            return redirect()->route('devices.index')
                ->with('error', 'Collega prima Android Enterprise.');
        }

        $googleDevices = $service->listDevices($company->android_enterprise_name);

        foreach ($googleDevices as $googleDevice) {
            Device::updateOrCreate(
                ['google_device_id' => $googleDevice->getName()],
                [
                    'company_id' => $company->id,
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
            ->with('success', 'Dispositivi sincronizzati.');
    }
}
