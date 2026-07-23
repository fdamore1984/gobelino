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
        $client->setAuthConfig(storage_path('app/google-amapi.json'));
        $client->addScope(AndroidManagement::ANDROIDMANAGEMENT);

        $this->service = new AndroidManagement($client);
        $this->projectId = env('AMAPI_PROJECT_ID');
    }

    /**
     * Step 1 del collegamento: genera l'URL a cui reindirizzare l'admin
     * per creare/collegare il suo Android Enterprise.
     */
    public function createSignupUrl(string $callbackUrl): SignupUrl
    {
        return $this->service->signupUrls->create([
            'projectId' => $this->projectId,
            'callbackUrl' => $callbackUrl,
        ]);
    }

    /**
     * Step 2: dopo il redirect di Google (con enterpriseToken e
     * signupUrlName nella query string), finalizza la creazione
     * dell'enterprise e restituisce il suo nome (enterprises/xxxx).
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
     * Crea (o aggiorna) una policy minimale da associare ai dispositivi.
     * Da qui in futuro si potranno aggiungere restrizioni specifiche.
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
     * Genera un token di iscrizione (da cui si ricava il QR code da
     * far scansionare al dispositivo Android in fase di provisioning).
     */
    public function createEnrollmentToken(string $enterpriseName, string $policyName): GoogleEnrollmentToken
    {
        $token = new GoogleEnrollmentToken([
            'policyName' => $policyName,
            'duration' => '3600s', // valido un'ora
        ]);

        return $this->service->enterprises_enrollmentTokens->create($enterpriseName, $token);
    }

    /**
     * Recupera l'elenco dei dispositivi già iscritti su Google per
     * quell'enterprise (usato per sincronizzare lo stato locale).
     */
    public function listDevices(string $enterpriseName): array
    {
        $response = $this->service->enterprises_devices->listEnterprisesDevices($enterpriseName);

        return $response->getDevices() ?? [];
    }
}
