<?php

namespace Ssntpl\CloudStorage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class CloudStorageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('cloud', function ($app, $config) {
            return new CloudStorageAdapter($config);
        });
    }
}
