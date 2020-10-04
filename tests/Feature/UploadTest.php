<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Tests\IntegrationTest;

class UploadTest extends IntegrationTest
{
    public function test()
    {
        $path = $this->path();
        $contents = $this->contents();
        $file = File::create($contents, $path);

        $file->upload();

        $this->testFile($file, $path, $contents, 'public');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('filesystems.cloud', 'public');
    }
}
