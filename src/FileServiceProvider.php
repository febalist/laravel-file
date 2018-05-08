<?php

namespace Febalist\Laravel\File;

use Febalist\Laravel\File\Commands\TempClear;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class FileServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/file.php');
        $this->loadViewsFrom(__DIR__.'/../views', 'file');
        if ($this->app->runningInConsole()) {
            $this->commands([
                TempClear::class,
            ]);
        }
    }
}
