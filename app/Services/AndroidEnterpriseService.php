<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\AndroidManagement;
use Google\Service\AndroidManagement\Enterprise;
use Google\Service\AndroidManagement\EnrollmentToken as GoogleEnrollmentToken;
use Google\Service\AndroidManagement\Policy;
use Google\Service\AndroidManagement\SignupUrl;

class AndroidEnterpriseService
{
    protected AndroidManagement $service;

    protected string $projectId;

    public function __construct()
    {
        $client = new GoogleClient();
        
        // 1. Check if credentials are passed via environment variable (for Railway)
        $credentialsEnv = env('GOOGLE_APPLICATION_CREDENTIALS_JSON');

        if (!empty($credentialsEnv)) {
            // Decode the JSON string provided by Railway
            $credentials = json_decode($credentialsEnv, true);
            
            // Safety check on JSON validity
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("The Google JSON in Railway is not valid: " . json_last_error_msg());
            }
            
            $client->setAuthConfig($credentials);
        } else {
            // 2. Fallback for local development: reads from the physical file
            $client->setAuthConfig(storage_path('app/google-amapi.json'));
        }

        $client->addScope(AndroidManagement::ANDROIDMANAGEMENT);

        $this->service = new AndroidManagement($client);
        $this->projectId = env('AMAPI_PROJECT_ID');
    }

    /**
     * Step 1 of the connection: generates the URL to redirect the admin
     * to in order to create/connect their Android Enterprise.
     */
    public function createSignupUrl(string $callbackUrl): SignupUrl
    {
        return $this->service->signupUrls->create([
            'projectId' => $this->projectId,
            'callbackUrl' => $callbackUrl,
        ]);
    }

    /**
     * Step 2: after Google's redirect (with enterpriseToken and
     * signupUrlName in the query string), finalizes the creation
     * of the enterprise and returns its name (enterprises/xxxx).
     */
    public function completeEnterpriseSignup(string $signupUrlName, string $enterpriseToken): Enterprise
    {
        $enterprise = new Enterprise([
            'enabledNotificationTypes' => [],
        ]);

        return $this->service->enterprises->create($enterprise, [
            'projectId' => $this->projectId,
            'signupUrlName' => $signupUrlName,
            'enterpriseToken' => $enterpriseToken,
        ]);
    }

    /**
     * Creates (or updates) a minimal policy to associate with devices.
     * Specific restrictions can be added here in the future.
     */
    public function ensureDefaultPolicy(string $enterpriseName): string
    {
        $policyName = $enterpriseName.'/policies/default';

        $policy = new Policy([
            'applications' => [],
        ]);

        $this->service->enterprises_policies->patch($policyName, $policy);

        return $policyName;
    }

    /**
     * Generates an enrollment token (from which the QR code is derived
     * to be scanned by the Android device during provisioning).
     */
    public function createEnrollmentToken(string $enterpriseName, string $policyName): GoogleEnrollmentToken
    {
        $token = new GoogleEnrollmentToken([
            'policyName' => $policyName,
            'duration' => '3600s', // valid for one hour
        ]);

        return $this->service->enterprises_enrollmentTokens->create($enterpriseName, $token);
    }

    /**
     * Retrieves the list of devices already enrolled on Google for
     * that enterprise (used to sync the local state).
     */
    public function listDevices(string $enterpriseName): array
    {
        $response = $this->service->enterprises_devices->listEnterprisesDevices($enterpriseName);

        return $response->getDevices() ?? [];
    }
}
