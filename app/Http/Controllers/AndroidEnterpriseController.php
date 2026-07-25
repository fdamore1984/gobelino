<?php

namespace App\Http\Controllers;

use App\Services\AndroidEnterpriseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AndroidEnterpriseController extends Controller
{
    /**
     * Starts the connection: generates the Google URL and redirects
     * the user there. Should be called from the "Connect Android
     * Enterprise" button in the Devices section.
     */
    public function create(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $callbackUrl = route('android-enterprise.callback');

        $signupUrl = $service->createSignupUrl($callbackUrl);

        // Save the signup URL name: needed later, when Google
        // redirects back, to complete the creation.
        $request->user()->company->update([
            'android_signup_url_name' => $signupUrl->getName(),
        ]);

        return redirect()->away($signupUrl->getUrl());
    }

    /**
     * Google redirects here after the admin completes the connection
     * on its interface, passing enterpriseToken in the query string.
     */
    public function callback(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $company = $request->user()->company;

        $enterpriseToken = $request->query('enterpriseToken');

        if (! $enterpriseToken || ! $company->android_signup_url_name) {
            return redirect()->route('devices.index')
                ->with('error', 'Connecting to Android Enterprise failed. Please try again.');
        }

        $enterprise = $service->completeEnterpriseSignup(
            $company->android_signup_url_name,
            $enterpriseToken
        );

        $company->update([
            'android_enterprise_name' => $enterprise->getName(),
            'android_signup_url_name' => null,
        ]);

        // Immediately create a basic policy, so the company can generate
        // enrollment tokens without additional steps.
        $service->ensureDefaultPolicy($enterprise->getName());

        return redirect()->route('devices.index')
            ->with('success', 'Android Enterprise connected successfully.');
    }
}
