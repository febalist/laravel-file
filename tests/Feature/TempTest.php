<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Tests\IntegrationTest;

class TempTest extends IntegrationTest
{
    public function test_without_contents()
    {
        $file = File::temp();

        $this->assertFalse($file->exists());
        $this->assertEquals(File::ROOT_DISK, $file->disk());
        $this->assertStringStartsWith(sys_get_temp_dir(), $file->path());

        $file->delete();
        $this->assertFalse($file->exists());
    }

    public function test_with_contents()
    {
        $contents = $this->contents();

        $file = File::temp($contents);

        $this->testFile($file, null, $contents);

        $file->delete();
        $this->assertFalse($file->exists());
    }

    public function test_name()
    {
        $name = $this->random();
        $file = File::temp('', $name);
        $this->assertEquals($name, $file->name());
    }
}
