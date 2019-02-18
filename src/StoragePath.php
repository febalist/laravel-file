<?php

namespace Febalist\Laravel\File;

use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Storage;

abstract class StoragePath
{
    protected $path;
    protected $disk;

    /** @return static|null */
    public static function load($path, $disk = 'default', $check = false)
    {
        $element = new static($path, $disk);

        if ($check && !$element->exists()) {
            return null;
        }

        return $element;
    }

    public function path()
    {
        return $this->path;
    }

    public function disk()
    {
        return $this->disk;
    }

    /** @return boolean */
    public function exists()
    {
        return $this->storage()->exists($this->path);
    }

    /** @return FilesystemAdapter */
    public function storage()
    {
        return Storage::disk($this->disk);
    }

    /** @return integer|null */
    public function timestamp()
    {
        return $this->exists() ? $this->storage()->getTimestamp($this->path) : null;
    }

    /** @return Carbon|null */
    public function time()
    {
        $timestamp = $this->timestamp();

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }
}
