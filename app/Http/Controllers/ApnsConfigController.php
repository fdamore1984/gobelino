<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApnsConfigController extends Controller
{
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
     * Genera un CSR per il certificato APNs
     */
    public function generateCsr(Request $request)
    {
        $company = auth()->user()->company;
        
        // Genera CSR (se non esiste già)
        $csr = $this->createCsr($company);
        
        return response()->json([
            'success' => true,
            'csr' => $csr,
            'message' => 'CSR generato con successo!',
        ]);
    }

    /**
     * Genera il CSR usando openssl
     */
    private function createCsr(Company $company)
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
            'commonName' => $company->name . ' MDM',
        ];

        // Genera CSR
        $csr = openssl_csr_new($dn, $res, $config);
        openssl_csr_export($csr, $csrOut);

        // Salva la chiave privata nel database/storage
        $company->apns_private_key = $privKey;
        $company->save();

        return $csrOut;
    }

    /**
     * Carica il certificato APNs firmato da Apple
     */
    public function uploadCertificate(Request $request)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pem,cer,crt',
        ]);

        $company = auth()->user()->company;
        $file = $request->file('certificate');

        // Leggi il contenuto del certificato
        $certificateContent = file_get_contents($file->getRealPath());

        // Valida il certificato
        if (!openssl_x509_read($certificateContent)) {
            return redirect()->back()
                ->with('error', 'Certificato non valido. Assicurati che sia un file .pem valido da Apple.');
        }

        // Salva il certificato
        $company->apns_certificate = $certificateContent;
        $company->apns_certificate_expires_at = $this->extractExpiryDate($certificateContent);
        $company->save();

        return redirect()->route('apns.configure')
            ->with('success', 'Certificato APNs caricato con successo!');
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
     * Scarica il CSR per mandarlo ad Apple
     */
    public function downloadCsr()
    {
        $company = auth()->user()->company;
        $csr = $this->createCsr($company);

        return response($csr)
            ->header('Content-Type', 'application/pkcs7-mime')
            ->header('Content-Disposition', 'attachment; filename="' . $company->name . '_MDM.csr"');
    }
}
