<?php

namespace App\Http\Controllers;

use App\Services\AndroidEnterpriseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AndroidEnterpriseController extends Controller
{
    /**
     * Avvia il collegamento: genera l'URL di Google e ci reindirizza
     * l'utente. Va chiamato dal pulsante "Collega Android Enterprise"
     * nella sezione Dispositivi.
     */
    public function create(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $callbackUrl = route('android-enterprise.callback');

        $signupUrl = $service->createSignupUrl($callbackUrl);

        // Salviamo il nome della signup URL: serve dopo, quando Google
        // reindirizza indietro, per completare la creazione.
        $request->user()->company->update([
            'android_signup_url_name' => $signupUrl->getName(),
        ]);

        return redirect()->away($signupUrl->getUrl());
    }

    /**
     * Google reindirizza qui dopo che l'admin ha completato il collegamento
     * sulla sua interfaccia, passando enterpriseToken nella query string.
     */
    public function callback(Request $request, AndroidEnterpriseService $service): RedirectResponse
    {
        $company = $request->user()->company;

        $enterpriseToken = $request->query('enterpriseToken');

        if (! $enterpriseToken || ! $company->android_signup_url_name) {
            return redirect()->route('devices.index')
                ->with('error', 'Collegamento ad Android Enterprise non riuscito. Riprova.');
        }

        $enterprise = $service->completeEnterpriseSignup(
            $company->android_signup_url_name,
            $enterpriseToken
        );

        $company->update([
            'android_enterprise_name' => $enterprise->getName(),
            'android_signup_url_name' => null,
        ]);

        // Crea subito una policy di base, così l'azienda può generare
        // token di iscrizione senza passaggi aggiuntivi.
        $service->ensureDefaultPolicy($enterprise->getName());

        return redirect()->route('devices.index')
            ->with('success', 'Android Enterprise collegato correttamente.');
    }
}
