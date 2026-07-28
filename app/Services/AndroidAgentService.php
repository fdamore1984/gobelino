<?php

namespace App\Services;

use App\Models\Company;
use App\Models\EnrollmentToken;
use Illuminate\Support\Str;

/**
 * Manages Android device enrollment through our own agent APK instead
 * of the Android Management API. The device becomes Device Owner via
 * Android's native QR provisioning flow (ACTION_PROVISIONING), which
 * does NOT require an Android Enterprise / EMM binding with Google.
 *
 * Communication with enrolled devices happens by polling (see
 * App\Http\Controllers\Api\DeviceAgentController), not FCM.
 */
class AndroidAgentService
{
    /**
     * Fully-qualified DeviceAdminReceiver component of the agent APK.
     * MUST be the absolute path (e.g. "com.gobelino.agent/com.gobelino.agent.receiver.AgentDeviceAdminReceiver")
     * to avoid Device Owner provisioning errors.
     */
    protected string $adminComponent;

    /**
     * SHA-256 checksum (URL-safe base64, no padding) of the agent APK
     * currently published, required by the provisioning flow to
     * verify the download before installing it.
     */
    protected ?string $apkChecksum;

    protected string $apkDownloadUrl;

    public function __construct()
    {
        // Forziamo il Fully Qualified Class Name bypassando il file .env
        // per prevenire errori di validazione durante l'enrollment.
        $this->adminComponent = 'com.gobelino.agent/com.gobelino.agent.receiver.AgentDeviceAdminReceiver';
        
        $this->apkChecksum = config('services.agent.apk_checksum');
        $this->apkDownloadUrl = config('services.agent.apk_download_url');
    }

    /**
     * Creates a new enrollment token for the company and builds the
     * JSON payload to encode as QR code. The device's Setup Wizard
     * reads these standard EXTRA_PROVISIONING_* keys natively.
     */
    public function createEnrollmentToken(Company $company, int $createdByUserId): EnrollmentToken
    {
        $token = Str::random(40);

        $adminExtras = [
            // Consumed by the agent app right after provisioning to
            // call POST /api/agent/enroll and obtain its device_token.
            'server_url' => rtrim(config('app.url'), '/'),
            'enrollment_token' => $token,
        ];

        $qrPayload = [
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => $this->adminComponent,
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION' => $this->apkDownloadUrl,
            // Assicurati che apkChecksum sia l'hash del file, non della firma
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_CHECKSUM' => $this->apkChecksum,
            'android.app.extra.PROVISIONING_SKIP_ENCRYPTION' => true,
            'android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED' => true,
            'android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE' => $adminExtras,
        ];

        return EnrollmentToken::create([
            'company_id' => $company->id,
            'created_by' => $createdByUserId,
            'platform' => 'android',
            'google_name' => '',
            'token' => $token,
            'apk_checksum' => $this->apkChecksum,
            'qr_code_json' => json_encode($qrPayload),
            'expires_at' => now()->addHours(24),
        ]);     
    }
}