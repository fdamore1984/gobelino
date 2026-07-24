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
     * Genera un nuovo token di iscrizione (QR code) da far scansionare
     * a un dispositivo Android o iOS in fase di provisioning/reset.
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
                ->with('error', 'Collega prima Android Enterprise per poter aggiungere dispositivi.');
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
                ->with('error', 'Per aggiungere iPhone/iPad devi prima configurare il certificato push APNs della tua azienda.');
        }

        $result = $service->createEnrollmentToken($company);

        $enrollmentToken = EnrollmentToken::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'platform' => 'ios',
            // Riusiamo google_name come identificatore locale univoco del
            // token (per iOS non esiste un "nome Google" da salvare qui).
            'google_name' => $result['local_token'],
            // Per iOS il "QR" incapsula l'URL del profilo, non un JSON.
            'qr_code_json' => $result['qr_payload'],
            'expires_at' => $result['expires_at'],
        ]);

        return view('devices.enroll', ['enrollmentToken' => $enrollmentToken]);
    }

    /**
     * Richiama l'Android Management API per aggiornare l'elenco
     * dispositivi locale con quelli realmente iscritti su Google.
     *
     * Nota: sincronizza solo i dispositivi Android. Per iOS non esiste
     * un equivalente "listDevices" da interrogare: lo stato dei device
     * Apple andrà aggiornato via check-in MDM (vedi IosMdmService),
     * non con una sync periodica come questa.
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
            ->with('success', 'Dispositivi sincronizzati.');
    }
}
