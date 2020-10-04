<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Tests\IntegrationTest;

class PutTest extends IntegrationTest
{
    public function test()
    {
        $path = $this->path();
        $contents = $this->contents();
        $source = File::create($contents, $path);

        $file = File::put($source, 'foo/bar');
        $this->assertInstanceOf(File::class, $file);
        $this->assertNotSame($file, $source);
        $this->testFile($file, 'foo/bar', $contents);
        $this->testFile($source, $path, $contents);

        $this->assertIsInt($file->size());
        $this->assertGreaterThan(0, $file->size());
        $this->assertEquals($source->size(), $file->size());
    }
}
