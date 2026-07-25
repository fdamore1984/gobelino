<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Gestisce la configurazione del certificato push APNs, prerequisito
 * per poter iscrivere iPhone/iPad (l'equivalente, per iOS, del
 * "Collega Android Enterprise" che esiste già per Android).
 *
 * A differenza di Android Enterprise, qui NON esiste un flusso OAuth a
 * cui reindirizzare l'utente: Apple richiede un processo in due parti,
 * in parte fuori dalla nostra app. Tutta la configurazione avviene in
 * un'unica pagina (apns.configure), con due soli passaggi lato utente:
 *
 *   Step 1: l'utente inserisce l'email con cui è registrato su
 *      https://mdmcert.download. Generiamo qui una coppia di chiavi +
 *      una CSR (Certificate Signing Request) e la inviamo, tramite
 *      l'API di mdmcert.download, a quella email: il servizio
 *      restituirà via mail il CSR firmato da Apple.
 *   Step 2 (fuori dalla nostra app): l'utente carica la CSR firmata
 *      ricevuta via mail su https://identity.apple.com/pushcert/
 *      (Apple Push Certificates Portal), che restituisce il
 *      certificato push finale (.pem).
 *   Step 2 (di nuovo qui): l'utente ricarica quel file .pem, che
 *      abbiniamo alla chiave privata generata allo Step 1 e salviamo
 *      sull'azienda.
 */
class ApnsController extends Controller
{
    private const MDMCERT_API_URL = 'https://mdmcert.download/api/v1/signrequest';

    public function show(Request $request): View
    {
        $company = $request->user()->company;

        return view('apns.configure', [
            'company' => $company,
        ]);
    }

    /**
     * Step 1: genera la coppia di chiavi RSA + la CSR per il
     * certificato push, genera anche il certificato "server" che
     * l'API di mdmcert.download richiede per cifrare la risposta, e
     * invia tutto a mdmcert.download insieme all'email indicata.
     *
     * La chiave privata viene salvata subito (cifrata) sull'azienda:
     * è la stessa che dovrà combaciare con il certificato che verrà
     * caricato allo Step 2. Finché il certificato non viene caricato,
     * hasApnsConfigured() resta false.
     */
    public function requestSignedCsr(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $company = $request->user()->company;

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        $privateKey = openssl_pkey_new($config);

        if ($privateKey === false) {
            return redirect()->route('apns.configure')
                ->with('error', 'Impossibile generare la chiave privata sul server. Riprova o contatta il supporto.');
        }

        $csrSubject = [
            'countryName' => 'IT',
            'organizationName' => $company->name,
            'commonName' => $company->name.' Push',
            'emailAddress' => $data['email'],
        ];

        $csr = openssl_csr_new($csrSubject, $privateKey, $config);

        openssl_csr_export($csr, $csrOut);
        openssl_pkey_export($privateKey, $keyOut);

        $serverCert = $this->generateServerCertificate($company);

        try {
            $response = Http::asForm()->post(self::MDMCERT_API_URL, [
                'csr' => base64_encode($csrOut),
                'email' => $data['email'],
                'key' => config('services.mdmcert.server_key'),
                'encrypt' => base64_encode($serverCert['cert']),
            ]);

            if ($response->failed()) {
                throw new \Exception('mdmcert.download ha risposto con un errore: '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::error('Errore durante l\'invio del CSR a mdmcert.download: '.$e->getMessage());

            return redirect()->route('apns.configure')
                ->with('error', 'Non è stato possibile inviare la richiesta a mdmcert.download. Riprova tra qualche minuto.');
        }

        // Salviamo tutto solo dopo che l'invio è andato a buon fine:
        // resta "in attesa" finché non carichiamo il certificato
        // firmato ottenuto da Apple (Step 2).
        $company->update([
            'apns_private_key_pem' => $keyOut,
            'apns_csr_pending' => $csrOut,
            'apns_server_cert' => $serverCert['cert'],
            'apns_server_key' => $serverCert['key'],
            'apns_mdmcert_email' => $data['email'],
            'apns_csr_submitted_at' => now(),
            'apns_certificate_pem' => null,
            'apns_topic' => null,
            'apns_expires_at' => null,
        ]);

        return redirect()->route('apns.configure')
            ->with('success', 'Richiesta inviata! Controlla la casella '.$data['email'].': mdmcert.download ti manderà il CSR firmato da caricare su identity.apple.com/pushcert.');
    }

    /**
     * Step 2: riceve il certificato push (.pem) ottenuto da Apple dopo
     * aver caricato su identity.apple.com/pushcert/ il CSR firmato
     * ricevuto da mdmcert.download, lo verifica contro la chiave
     * privata generata allo Step 1 e lo salva sull'azienda.
     */
    public function uploadCertificate(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'certificate' => ['required', 'file', 'max:512'],
        ]);

        if (empty($company->apns_private_key_pem)) {
            return redirect()->route('apns.configure')
                ->with('error', 'Completa prima lo Step 1 qui sopra, poi torna a caricare il certificato firmato da Apple.');
        }

        $certPem = file_get_contents($data['certificate']->getRealPath());

        $certInfo = @openssl_x509_parse($certPem);

        if (! $certInfo) {
            return redirect()->route('apns.configure')
                ->with('error', 'Il file caricato non è un certificato .pem valido.');
        }

        if (! openssl_x509_check_private_key($certPem, $company->apns_private_key_pem)) {
            return redirect()->route('apns.configure')
                ->with('error', 'Questo certificato non corrisponde alla richiesta generata da questa azienda. Assicurati di aver seguito lo Step 1 qui sopra prima di caricarlo.');
        }

        // Il topic APNs per i profili MDM è tipicamente esposto nel
        // campo UID (userIdentifier) del subject del certificato,
        // es. "com.apple.mgmt.External.<UUID>".
        $topic = $certInfo['subject']['UID'] ?? null;
        $expiresAt = Carbon::createFromTimestamp($certInfo['validTo_time_t']);

        $company->update([
            'apns_certificate_pem' => $certPem,
            'apns_topic' => $topic,
            'apns_expires_at' => $expiresAt,
            'apns_csr_pending' => null,
        ]);

        return redirect()->route('apns.configure')
            ->with('success', 'Certificato push APNs configurato correttamente (valido fino al '.$expiresAt->format('d/m/Y').').');
    }

    /**
     * Genera il certificato "server" auto-firmato richiesto dall'API di
     * mdmcert.download nel campo `encrypt` (usato dal servizio per
     * cifrare eventuali dati sensibili nella risposta).
     */
    private function generateServerCertificate(Company $company): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        openssl_pkey_export($privateKey, $keyOut);

        $dn = [
            'countryName' => 'IT',
            'organizationName' => $company->name,
            'commonName' => $company->name.'.mdmcert.download',
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365, $config);
        openssl_x509_export($cert, $certOut);

        return [
            'cert' => $certOut,
            'key' => $keyOut,
        ];
    }
}
