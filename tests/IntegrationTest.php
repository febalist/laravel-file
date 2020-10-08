<?php

namespace Tests;

use Febalist\Laravel\File\File;
use Febalist\Laravel\File\FileServiceProvider;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class IntegrationTest extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [FileServiceProvider::class];
    }

    protected function path()
    {
        $pieces = [];

        foreach (range(2, 4) as $i) {
            $pieces[] = $this->random(1, 8);
        }

        return implode('/', $pieces).'.'.$this->random(1, 4);
    }

    protected function contents()
    {
        return $this->random(64, 128);
    }

    protected function random($min = 8, $max = 128)
    {
        return Str::random(random_int($min, $max));
    }

    protected function testFile(File $file, $path = null, $contents = null, $disk = null)
    {
        $this->assertTrue($file->exists());

        if ($contents) {
            $this->assertEquals($contents, $file->read());
        }

        if ($path) {
            $this->assertEquals($path, $file->path());
            $this->assertEquals(pathinfo($path, PATHINFO_DIRNAME), $file->directory());
            $this->assertEquals(pathinfo($path, PATHINFO_BASENAME), $file->name());
            $this->assertEquals(pathinfo($path, PATHINFO_EXTENSION), $file->extension());
        }

        if ($disk) {
            $this->assertEquals($disk, $file->disk());
        }
    }
}
