<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Su Railway non carichiamo il file storage/app/google-amapi.json a
     * mano: il suo contenuto viene passato come variabile d'ambiente
     * GOOGLE_AMAPI_JSON, e qui lo scriviamo su disco ad ogni avvio se
     * il file non esiste ancora.
     */
    public function boot(): void
    {
        $json = env('GOOGLE_AMAPI_JSON');
        $path = storage_path('app/google-amapi.json');

        if ($json && ! file_exists($path)) {
            file_put_contents($path, $json);
        }
        
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        
    }
}
