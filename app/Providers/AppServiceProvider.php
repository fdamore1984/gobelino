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
     * On Railway we don't load the storage/app/google-amapi.json file
     * by hand: its content is passed as the GOOGLE_AMAPI_JSON
     * environment variable, and here we write it to disk on every
     * startup if the file doesn't already exist.
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
