<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Gestisce la configurazione del certificato push APNs, prerequisito
 * per poter iscrivere iPhone/iPad (l'equivalente, per iOS, del
 * "Collega Android Enterprise" che esiste già per Android).
 *
 * A differenza di Android Enterprise, qui NON esiste un flusso OAuth a
 * cui reindirizzare l'utente: Apple richiede un processo manuale in
 * due parti che avviene in parte fuori dalla nostra app:
 *
 *   1. Generiamo qui una coppia di chiavi + una CSR (Certificate
 *      Signing Request) e la facciamo scaricare all'utente.
 *   2. L'utente deve far *firmare* quella CSR con un certificato
 *      "vendor" MDM. Se l'azienda non ha già un account Apple
 *      Developer Enterprise Program con vendor certificate proprio,
 *      può usare un servizio di firma CSR condiviso come
 *      https://mdmcert.download (richiede solo un Apple ID).
 *   3. La CSR firmata va caricata su
 *      https://identity.apple.com/pushcert/ (Apple Push Certificates
 *      Portal), che restituisce il certificato push finale (.pem).
 *   4. Quel file va ricaricato qui sotto: lo abbiniamo alla chiave
 *      privata generata al passo 1 e salviamo tutto sull'azienda.
 */
class ApnsController extends Controller
{
    public function create(Request $request): View
    {
        return view('apns.connect', [
            'company' => $request->user()->company,
        ]);
    }

    /**
     * Genera la coppia di chiavi RSA + la CSR da far firmare da Apple
     * (tramite vendor cert proprio o un servizio come mdmcert.download).
     *
     * La chiave privata viene salvata subito (cifrata) sull'azienda:
     * è la stessa che dovrà combaciare con il certificato che verrà
     * caricato al passo successivo. Finché il certificato non viene
     * caricato, hasApnsConfigured() resta false.
     */
    public function generateCsr(Request $request): Response
    {
        $company = $request->user()->company;

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        $privateKey = openssl_pkey_new($config);

        if ($privateKey === false) {
            return redirect()->route('apns.connect')
                ->with('error', 'Impossibile generare la chiave privata sul server. Riprova o contatta il supporto.');
        }

        $csrSubject = [
            'countryName' => 'IT',
            'organizationName' => $company->name,
            'commonName' => $company->name.' Push',
            'emailAddress' => $request->user()->email,
        ];

        $csr = openssl_csr_new($csrSubject, $privateKey, $config);

        openssl_csr_export($csr, $csrOut);
        openssl_pkey_export($privateKey, $keyOut);

        // Salviamo subito la chiave privata: resterà "in attesa" finché
        // non carichiamo il certificato corrispondente firmato da Apple.
        $company->update([
            'apns_private_key_pem' => $keyOut,
            'apns_certificate_pem' => null,
            'apns_topic' => null,
            'apns_expires_at' => null,
        ]);

        return response($csrOut, 200, [
            'Content-Type' => 'application/pkcs10',
            'Content-Disposition' => 'attachment; filename="gobelino-apns-request.csr"',
        ]);
    }

    /**
     * Riceve il certificato push (.pem) ottenuto da Apple dopo aver
     * caricato la CSR firmata su identity.apple.com/pushcert/, lo
     * verifica contro la chiave privata generata al passo precedente
     * e lo salva sull'azienda.
     */
    public function upload(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'certificate' => ['required', 'file', 'max:512'],
        ]);

        if (empty($company->apns_private_key_pem)) {
            return redirect()->route('apns.connect')
                ->with('error', 'Genera prima la richiesta di firma (CSR) qui sotto, poi torna a caricare il certificato firmato da Apple.');
        }

        $certPem = file_get_contents($data['certificate']->getRealPath());

        $certInfo = @openssl_x509_parse($certPem);

        if (! $certInfo) {
            return redirect()->route('apns.connect')
                ->with('error', 'Il file caricato non è un certificato .pem valido.');
        }

        if (! openssl_x509_check_private_key($certPem, $company->apns_private_key_pem)) {
            return redirect()->route('apns.connect')
                ->with('error', 'Questo certificato non corrisponde alla chiave privata generata da questa azienda. Assicurati di aver caricato la CSR generata qui e non un\'altra.');
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
        ]);

        return redirect()->route('devices.index')
            ->with('success', 'Certificato push APNs configurato correttamente (valido fino al '.$expiresAt->format('d/m/Y').').');
    }
}
