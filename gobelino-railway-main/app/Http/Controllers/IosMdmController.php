<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentToken;
use App\Services\IosMdmService;
use Illuminate\Http\Response;

/**
 * Endpoint pubblico (nessuna sessione utente: lo apre Safari sul device
 * iOS/iPadOS dopo la scansione del QR) che serve il profilo di enrollment.
 *
 * NOTA: risponde ancora con un errore esplicito finché
 * IosMdmService::buildSignedMobileconfig() non sarà implementato
 * (serve il certificato vendor + push APNs, vedi quel file).
 */
class IosMdmController extends Controller
{
    public function profile(string $token, IosMdmService $service): Response
    {
        // Per i token iOS riusiamo la colonna google_name (inutilizzata,
        // essendo pensata per il nome Google enterprises/.../enrollmentTokens/...)
        // come identificatore locale univoco del token.
        $enrollmentToken = EnrollmentToken::where('platform', 'ios')
            ->where('google_name', $token)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->firstOrFail();

        $company = $enrollmentToken->company;

        $mobileconfig = $service->buildSignedMobileconfig($company, $token);

        return response($mobileconfig, 200, [
            'Content-Type' => 'application/x-apple-aspen-config',
            'Content-Disposition' => 'attachment; filename="enrollment.mobileconfig"',
        ]);
    }
}
