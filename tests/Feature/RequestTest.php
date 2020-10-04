<?php

namespace Tests\Feature;

use Febalist\Laravel\File\File;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\IntegrationTest;

class RequestTest extends IntegrationTest
{
    public function test()
    {
        $this->app['router']->get('upload', [
            'uses' => function () {
                return 'ok';
            },
        ]);

        $foo = File::temp($this->contents(), 'foo.txt');
        $bar = File::temp($this->contents(), 'bar.txt');

        $this->call('POST', 'upload', [], [], [
            'foo' => new UploadedFile($foo->path(), $foo->name()),
            'bar' => new UploadedFile($bar->path(), $bar->name()),
        ]);

        $files = File::request();
        $this->assertInstanceOf(Collection::class, $files);
        $this->testFile($files['foo.txt'], $foo->path(), $foo->read());
        $this->testFile($files['bar.txt'], $bar->path(), $bar->read());

        foreach (['foo', 'bar'] as $key) {
            $files = File::request($key);
            $this->assertInstanceOf(Collection::class, $files);
            $this->assertEquals(1, $files->count());
            $this->testFile($files["$key.txt"], $$key->path(), $$key->read());
        }
    }
}
