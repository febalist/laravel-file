<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Tests\IntegrationTest;

class CreateTest extends IntegrationTest
{
    public function test()
    {
        $path = $this->path();
        $contents = $this->contents();

        $file = File::create($contents, $path);

        $this->testFile($file, $path, $contents);

        $file->delete();
        $this->assertFalse($file->exists());
    }
}
