<?php

namespace Febalist\Laravel\File;

class Directory extends StoragePath
{
    public function __construct($path, $disk)
    {
        $this->path = File::pathJoin($path);
        $this->disk = File::diskName($disk);
    }

    public function delete()
    {
        $this->storage()->deleteDirectory($this->path);
    }

    public function create()
    {
        $this->storage()->createDir($this->path);
    }
}
