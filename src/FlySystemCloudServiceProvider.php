<?php

namespace Ssntpl\FlySystemCloud;

use Illuminate\Support\ServiceProvider;
use Ssntpl\FlySystemCloud\Cloud\CloudAdapter;

class FlySystemCloudServiceProvider extends ServiceProvider
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
        \Storage::extend('cloud', function ($app, $config) {
            return new CloudAdapter($config['cache_time'],$config['cache_disk'], $config['remote_disks']);
        });
    }
}
