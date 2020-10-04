<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Tests\IntegrationTest;

class LoadTest extends IntegrationTest
{
    public function test_url()
    {
        $file = File::load('https://via.placeholder.com/100');

        $this->testFile($file);
        $this->assertEquals(File::ROOT_DISK, $file->disk());
        $this->assertStringStartsWith(sys_get_temp_dir(), $file->path());
    }

    public function test_self()
    {
        $contents = $this->contents();

        $source = File::temp($contents)->store();
        $file = File::load($source);

        $this->assertInstanceOf(File::class, $file);
        $this->assertNotSame($file, $source);
        $this->assertEquals($source->disk(), $file->disk());
        $this->testFile($file, $source->path(), $contents);
    }
}
