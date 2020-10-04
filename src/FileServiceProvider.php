<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class FileServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        Config::set('filesystems.disks.'.File::ROOT_DISK, [
            'driver' => 'local',
            'root' => '/',
        ]);
    }
}
