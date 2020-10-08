<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Illuminate\Support\Facades\Config;
use Tests\IntegrationTest;

class UploadTest extends IntegrationTest
{
    public function test()
    {
        $path = $this->path();
        $contents = $this->contents();
        $file = File::create($contents, $path);

        $file->upload();

        $this->testFile($file, $path, $contents, Config::get('filesystems.cloud'));

        $file->delete();

        $this->assertFalse($file->exists());
    }
}
