<?php

namespace App\Providers;


use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;



class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // // Comentado temporalmente para desarrollo
        /// Forzar HTTPS cuando se usa ngrok
        //if (str_contains(config('app.url'), 'ngrok')) {
        //    URL::forceScheme('https');
        //}
    }
}
