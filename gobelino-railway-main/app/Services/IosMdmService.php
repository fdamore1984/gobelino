<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Str;

/**
 * Gestisce l'iscrizione (enrollment) di iPhone/iPad tramite il
 * protocollo Apple MDM.
 *
 * A differenza di Android (dove Google fa da server MDM per noi tramite
 * Android Management API), qui il server MDM siamo NOI: dobbiamo firmare
 * il profilo di configurazione, esporre gli endpoint di check-in e
 * inviare le push APNs per svegliare i device.
 *
 * STATO ATTUALE: stub funzionante solo per il flusso "genera QR",
 * ma la generazione vera del profilo firmato richiede due prerequisiti
 * che ancora mancano (vedi Company::hasApnsConfigured()):
 *
 *   1. Un certificato "MDM Vendor" (CSR firmato da Apple, richiesto
 *      come Apple Developer Enterprise con account flaggato per uso MDM).
 *   2. Un certificato push APNs, generato firmando un CSR con il
 *      certificato vendor e caricandolo su https://identity.apple.com/pushcert/
 *
 * TODO quando i certificati saranno disponibili:
 *   - implementare buildMobileconfig() per generare il plist di enrollment
 *     (PayloadType: Profile Service o direttamente MDM payload)
 *   - firmarlo in CMS/PKCS#7 con il certificato vendor (openssl smime -sign)
 *   - implementare gli endpoint /mdm/checkin (Authenticate, TokenUpdate,
 *     CheckOut) e /mdm/command (coda comandi) — vedi Apple MDM Protocol
 *     Reference: https://developer.apple.com/documentation/devicemanagement
 *   - implementare l'invio della push "wake up" tramite ApnsService
 */
class IosMdmService
{
    /**
     * Crea un "token" di iscrizione iOS locale (non esiste un equivalente
     * Google da chiamare: qui il token è nostro, generato e conservato
     * solo nel nostro database).
     *
     * Restituisce l'URL pubblico del profilo di enrollment da incapsulare
     * nel QR code. È questo URL che l'iPhone/iPad apre in Safari dopo la
     * scansione, per scaricare e installare il profilo .mobileconfig.
     */
    public function createEnrollmentToken(Company $company): array
    {
        if (! $company->hasApnsConfigured()) {
            throw new \RuntimeException(
                'Impossibile generare un enrollment iOS: manca il certificato push APNs per questa azienda.'
            );
        }

        $localToken = (string) Str::uuid();

        // L'URL che verrà messo nel QR. Il controller che risponde a questa
        // rotta (da implementare) deve restituire il .mobileconfig firmato
        // con Content-Type: application/x-apple-aspen-config.
        $profileUrl = route('ios-mdm.profile', ['token' => $localToken]);

        return [
            'local_token' => $localToken,
            'qr_payload' => $profileUrl,
            // I profili di enrollment Apple non hanno una scadenza propria
            // come i token Android; qui applichiamo una scadenza "nostra"
            // lato applicazione, oltre la quale il link smette di funzionare.
            'expires_at' => now()->addHour(),
        ];
    }

    /**
     * TODO: da implementare quando saranno disponibili vendor cert e
     * certificato push. Deve generare il plist del profilo di enrollment
     * MDM e restituirlo firmato in CMS (PKCS#7 detached o attached).
     */
    public function buildSignedMobileconfig(Company $company, string $localToken): string
    {
        throw new \RuntimeException(
            'buildSignedMobileconfig() non ancora implementato: serve prima il certificato vendor MDM e il certificato push APNs.'
        );
    }
}
