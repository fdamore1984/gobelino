<?php

namespace App\Http\Controllers;

use App\Models\EnrollmentToken;
use App\Services\IosMdmService;
use Illuminate\Http\Response;

/**
 * Public endpoint (no user session: it's opened by Safari on the
 * iOS/iPadOS device after scanning the QR code) that serves the enrollment
 * profile.
 *
 * NOTE: it still responds with an explicit error until
 * IosMdmService::buildSignedMobileconfig() is implemented
 * (requires the vendor certificate + push APNs, see that file).
 */
class IosMdmController extends Controller
{
    public function profile(string $token, IosMdmService $service): Response
    {
        // For iOS tokens we reuse the google_name column (unused,
        // as it was meant for the Google name enterprises/.../enrollmentTokens/...)
        // as the local unique identifier of the token.
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
