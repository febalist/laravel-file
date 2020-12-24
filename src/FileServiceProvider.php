<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class FileServiceProvider extends IlluminateServiceProvider
{
    public function register()
    {
        Route::group([
            'prefix' => 'file',
            'middleware' => 'web',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    public function boot()
    {
        Config::set('filesystems.disks.'.File::ROOT_DISK, [
            'driver' => 'local',
            'root' => '/',
        ]);
    }
}
