<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class FileServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
    }

    public function register()
    {
        $this->configure();
    }

    protected function configure()
    {
        Config::set('filesystems.disks.'.File::ROOT_DISK, [
            'driver' => 'local',
            'root' => '/',
        ]);
    }

    protected function registerRoutes()
    {
        Route::group([
            'prefix' => 'file',
            'namespace' => 'Febalist\Laravel\File\Http\Controllers',
            'middleware' => 'web',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}
