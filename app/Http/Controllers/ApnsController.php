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
 * Handles the configuration of the APNs push certificate, a prerequisite
 * for enrolling iPhone/iPad devices (the iOS equivalent of the
 * "Connect Android Enterprise" flow that already exists for Android).
 *
 * Unlike Android Enterprise, there is NO OAuth flow here to
 * redirect the user to: Apple requires a two-part process,
 * partly outside our app. The whole configuration happens on a
 * single page (apns.configure), with only two steps on the user's side:
 *
 *   Step 1: the user enters the email they're registered with on
 *      https://mdmcert.download. Here we generate a key pair +
 *      a CSR (Certificate Signing Request) and send it, via
 *      the mdmcert.download API, to that email: the service
 *      will send back the CSR signed by Apple via email.
 *   Step 2 (outside our app): the user uploads the signed CSR
 *      received by email to https://identity.apple.com/pushcert/
 *      (Apple Push Certificates Portal), which returns the
 *      final push certificate (.pem).
 *   Step 2 (back here): the user uploads that .pem file, which we
 *      pair with the private key generated in Step 1 and save
 *      on the company.
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
     * Step 1: generates the RSA key pair + the CSR for the
     * push certificate, also generates the "server" certificate that
     * the mdmcert.download API requires to encrypt the response, and
     * sends everything to mdmcert.download along with the given email.
     *
     * The private key is saved immediately (encrypted) on the company:
     * it's the one that must match the certificate that will be
     * uploaded in Step 2. Until the certificate is uploaded,
     * hasApnsConfigured() stays false.
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
                ->with('error', 'Unable to generate the private key on the server. Please try again or contact support.');
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
                throw new \Exception('mdmcert.download responded with an error: '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::error('Error while sending the CSR to mdmcert.download: '.$e->getMessage());

            return redirect()->route('apns.configure')
                ->with('error', 'It was not possible to send the request to mdmcert.download. Please try again in a few minutes.');
        }

        // We only save everything after the request succeeds:
        // it stays "pending" until we upload the signed
        // certificate obtained from Apple (Step 2).
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
            ->with('success', 'Request sent! Check the inbox for '.$data['email'].': mdmcert.download will send you the signed CSR to upload to identity.apple.com/pushcert.');
    }

    /**
     * Step 2: receives the push certificate (.pem) obtained from Apple
     * after uploading the signed CSR received from mdmcert.download to
     * identity.apple.com/pushcert/, verifies it against the private
     * key generated in Step 1, and saves it on the company.
     */
    public function uploadCertificate(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'certificate' => ['required', 'file', 'max:512'],
        ]);

        if (empty($company->apns_private_key_pem)) {
            return redirect()->route('apns.configure')
                ->with('error', 'Complete Step 1 above first, then come back and upload the certificate signed by Apple.');
        }

        $certPem = file_get_contents($data['certificate']->getRealPath());

        $certInfo = @openssl_x509_parse($certPem);

        if (! $certInfo) {
            return redirect()->route('apns.configure')
                ->with('error', 'The uploaded file is not a valid .pem certificate.');
        }

        if (! openssl_x509_check_private_key($certPem, $company->apns_private_key_pem)) {
            return redirect()->route('apns.configure')
                ->with('error', 'This certificate does not match the request generated by this company. Make sure you followed Step 1 above before uploading it.');
        }

        // The APNs topic for MDM profiles is typically exposed in
        // the UID (userIdentifier) field of the certificate subject,
        // e.g. "com.apple.mgmt.External.<UUID>".
        $topic = $certInfo['subject']['UID'] ?? null;
        $expiresAt = Carbon::createFromTimestamp($certInfo['validTo_time_t']);

        $company->update([
            'apns_certificate_pem' => $certPem,
            'apns_topic' => $topic,
            'apns_expires_at' => $expiresAt,
            'apns_csr_pending' => null,
        ]);

        return redirect()->route('apns.configure')
            ->with('success', 'APNs push certificate configured successfully (valid until '.$expiresAt->format('d/m/Y').').');
    }

    /**
     * Generates the self-signed "server" certificate required by the
     * mdmcert.download API in the `encrypt` field (used by the service
     * to encrypt any sensitive data in the response).
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
