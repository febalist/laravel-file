<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class FileServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/file.php');
    }
}
