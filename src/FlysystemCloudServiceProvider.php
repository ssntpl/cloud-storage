<?php

namespace Ssntpl\FlysystemCloud;

use Illuminate\Support\ServiceProvider;
use Ssntpl\FlysystemCloud\Cloud\CloudAdapter;

class FlysystemCloudServiceProvider extends ServiceProvider
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
