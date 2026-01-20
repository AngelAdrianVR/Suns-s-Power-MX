<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ESTA ES LA CLAVE:
        // Le decimos a Laravel: "No busques una carpeta 'public', 
        // la carpeta pública es la misma carpeta base donde estoy corriendo (public_html)".
        // $this->app->usePublicPath($this->app->basePath());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Como ya definimos arriba que nuestra "public" es la raíz del proyecto,
        // ahora solo le decimos que busque en la carpeta 'build' dentro de ahí.
        // Ruta resultante: /home1/germanac/public_html/build
        // Vite::useBuildDirectory('build');
    }
}