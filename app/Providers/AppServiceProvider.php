<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\File;
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
     */
    public function boot(): void
    {
        foreach ([storage_path('fonts'), storage_path('app/private/pdf-tmp')] as $directory) {
            File::ensureDirectoryExists($directory, 0700);
        }
        if ($proxies = config('iyawe.trusted_proxies')) {
            TrustProxies::at($proxies);
        }
        config(['dompdf.options.temp_dir' => storage_path('app/private/pdf-tmp')]);
    }
}
