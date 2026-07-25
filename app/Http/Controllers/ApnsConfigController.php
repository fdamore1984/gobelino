<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApnsConfigController extends Controller
{
    private const MDMCERT_API_URL = 'https://mdmcert.download/api/v1/signrequest';
    private const MDMCERT_SERVER_KEY = 'f847aea2ba06b41264d587b229e2712c89b1490a1208b7ff1aafab5bb40d47bc';

    /**
     * Mostra la pagina di configurazione APNs
     */
    public function show()
    {
        $company = auth()->user()->company;
        $hasApnsCertificate = $company->hasApnsConfigured();
        
        return view('apns.configure', [
            'company' => $company,
            'hasApnsCertificate' => $hasApnsCertificate,
        ]);
    }

    /**
     * Step 1: Genera il CSR e il server certificate
     */
    public function generateCsrAndEncryptionCert(Request $request)
    {
        $company = auth()->user()->company;
        
        try {
            // Step 1: Genera server certificate per l'encryption
            $serverCert = $this->generateServerCertificate($company);
            
            // Step 2: Genera CSR per APNs
            $csr = $this->generateApnsCsr($company);
            
            // Salva entrambi nel database
            $company->apns_server_cert = $serverCert['cert'];
            $company->apns_server_key = $serverCert['key'];
            $company->apns_csr_pending = $csr;
            $company->save();
            
            return response()->json([
                'success' => true,
                'message' => 'CSR e certificate generati! Ora inviamo a mdmcert.download...',
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating CSR: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nella generazione: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Invia il CSR a mdmcert.download
     */
    public function submitToMdmcert(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $company = auth()->user()->company;
        
        if (!$company->apns_csr_pending || !$company->apns_server_cert) {
            return response()->json([
                'success' => false,
                'message' => 'CSR non trovato. Genera prima il CSR.',
            ], 400);
        }

        try {
            // Prepara il payload per mdmcert.download
            $payload = [
                'csr' => base64_encode($company->apns_csr_pending),
                'email' => $request->email,
                'key' => self::MDMCERT_SERVER_KEY,
                'encrypt' => base64_encode($company->apns_server_cert),
            ];

            // Invia a mdmcert.download
            $response = Http::post(self::MDMCERT_API_URL, $payload);

            if ($response->failed()) {
                throw new \Exception('mdmcert.download API error: ' . $response->body());
            }

            // Salva lo stato di attesa
            $company->apns_mdmcert_email = $request->email;
            $company->apns_awaiting_signed_csr = true;
            $company->apns_csr_submitted_at = now();
            $company->save();

            return response()->json([
                'success' => true,
                'message' => 'CSR inviato a mdmcert.download! Controlla la email ' . $request->email . ' per il CSR firmato.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting to mdmcert: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Errore nell\'invio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3: Carica il CSR firmato (decriptato) da mdmcert.download
     */
    public function uploadSignedCsr(Request $request)
    {
        $request->validate([
            'signed_csr' => 'required|file|mimes:pem,p7,p7m,txt',
        ]);

        $company = auth()->user()->company;
        $file = $request->file('signed_csr');
        $signedCsr = file_get_contents($file->getRealPath());

        try {
            // Salva il CSR firmato
            $company->apns_signed_csr = $signedCsr;
            $company->apns_awaiting_signed_csr = false;
            $company->save();

            return redirect()->route('apns.configure')
                ->with('success', 'CSR firmato caricato! Ora carica il certificato finale da Apple.');
        } catch (\Exception $e) {
            Log::error('Error uploading signed CSR: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Errore nel caricamento: ' . $e->getMessage());
        }
    }

    /**
     * Step 4: Carica il certificato APNs finale da Apple
     */
    public function uploadApnsCertificate(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pem,cer,crt',
        ]);

        $company = auth()->user()->company;
        $file = $request->file('certificate');
        $certificateContent = file_get_contents($file->getRealPath());

        try {
            // Valida il certificato
            if (!openssl_x509_read($certificateContent)) {
                return redirect()->back()
                    ->with('error', 'Certificato non valido. Assicurati che sia un file .pem valido da Apple.');
            }

            // Salva il certificato
            $company->apns_certificate = $certificateContent;
            $company->apns_certificate_expires_at = $this->extractExpiryDate($certificateContent);
            $company->apns_csr_pending = null;
            $company->apns_signed_csr = null;
            $company->save();

            return redirect()->route('apns.configure')
                ->with('success', '✅ Certificato APNs caricato con successo! Sei pronto per mandare notifiche push!');
        } catch (\Exception $e) {
            Log::error('Error uploading certificate: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Errore nel caricamento: ' . $e->getMessage());
        }
    }

    /**
     * Genera il server certificate per l'encryption
     */
    private function generateServerCertificate(Company $company)
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Genera coppia di chiavi
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);

        // Dati per il certificato
        $dn = [
            'countryName' => 'IT',
            'organizationName' => $company->name,
            'commonName' => $company->name . '.mdmcert.download',
        ];

        // Genera CSR per il server certificate
        $csr = openssl_csr_new($dn, $res, $config);
        
        // Firma il CSR con la sua stessa chiave (self-signed, valido 365 giorni)
        $cert = openssl_csr_sign($csr, null, $res, 365, $config);
        openssl_x509_export($cert, $certPem);

        return [
            'cert' => $certPem,
            'key' => $privKey,
        ];
    }

    /**
     * Genera il CSR per APNs
     */
    private function generateApnsCsr(Company $company)
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Genera coppia di chiavi
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);

        // Dati per il CSR
        $dn = [
            'countryName' => 'IT',
            'organizationName' => $company->name,
            'commonName' => $company->name . ' MDM Push Certificate',
        ];

        // Genera CSR
        $csr = openssl_csr_new($dn, $res, $config);
        openssl_csr_export($csr, $csrOut);

        // Salva la chiave privata per il CSR
        $company->apns_csr_private_key = $privKey;

        return $csrOut;
    }

    /**
     * Estrae la data di scadenza dal certificato
     */
    private function extractExpiryDate($certificate)
    {
        $cert = openssl_x509_parse($certificate);
        return \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t']);
    }

    /**
     * Scarica il CSR per il debug
     */
    public function downloadCsr()
    {
        $company = auth()->user()->company;
        
        if (!$company->apns_csr_pending) {
            abort(404, 'CSR non trovato');
        }

        return response($company->apns_csr_pending)
            ->header('Content-Type', 'application/pkcs10')
            ->header('Content-Disposition', 'attachment; filename="' . $company->name . '_APNs.csr"');
    }
}
