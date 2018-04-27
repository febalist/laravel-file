<?php

namespace Febalist\Laravel\File;

use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File as IlluminateFile;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Storage;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class File
{
    public $disk;
    public $path;

    public function __construct($disk, $path)
    {
        $this->disk = $disk;
        $this->path = $path;
    }

    /** @return static|null */
    public static function load($path, $disk = null)
    {
        $disk = static::diskName($disk);

        $file = new static($disk, $path);

        if (!$file->exists()) {
            return null;
        }

        return $file;
    }

    /** @return static */
    public static function put($file, $disk = null, $dir = '', $name = null)
    {
        $resource = false;
        if (is_resource($file)) {
            $resource = true;
        } elseif (!$file instanceof SymfonyFile) {
            $file = new IlluminateFile($file);
        }

        $disk = static::diskName($disk);

        if (!$name) {
            if ($file instanceof SymfonyFile) {
                $name = $file->getFilename();
                if ($file instanceof UploadedFile) {
                    $name = $file->getClientOriginalName() ?: $name;
                }
            }
            if (!$name) {
                throw new Exception('Unknown file name');
            }
        }

        if ($resource) {
            $dir = $dir ? str_finish($dir, '/') : '';
            $path = Storage::disk($disk)->putStream($dir.$name, $resource);
        } else {
            $path = Storage::disk($disk)->putFileAs($dir, $file, $name);
        }

        return new static($disk, $path);
    }

    /** @return static */
    public static function request($key, $disk = null, $dir = '', $name = null)
    {
        return static::put(request()->file($key), $disk, $dir, $name);
    }

    protected static function diskName($name = null)
    {
        if (!$name || $name == 'default') {
            return config('filesystems.default');
        } elseif ($name == 'cloud') {
            return config('filesystems.cloud');
        }

        return $name;
    }

    /** @return boolean */
    public function exists()
    {
        return $this->storage()->exists($this->path);
    }

    /** @return static */
    public function copy($disk = null, $dir = '', $name = null)
    {
        $disk = static::diskName($disk);
        $name = $name ?: $this->name();

        return static::put($this->stream(), $disk, $dir, $name ?: $this->name());
    }

    public function delete()
    {
        $this->storage()->delete($this->path);
    }

    /** @return static */
    public function move($disk = null, $dir = '', $name = null)
    {
        $disk = static::diskName($disk);
        $dir = $dir ? str_finish($dir, '/') : '';
        $path = $dir.$name;

        if ($disk == $this->disk) {
            $this->storage()->move($this->path, $path);
        } else {
            $this->copy($disk, $dir, $name);
            $this->delete();
        }

        $this->disk = $disk;
        $this->path = $path;

        return $this;
    }

    /** @return static */
    public function rename($name)
    {
        $dir = $this->dir();
        $dir = $dir ? str_finish($dir, '/') : '';
        $path = $dir.$name;

        $this->storage()->rename($this->path, $path);

        $this->path = $path;

        return $this;
    }

    /** @return static */
    public function cloud($dir = '', $name = null)
    {
        return $this->move('cloud', $dir);
    }

    /** @return boolean */
    public function local()
    {
        return (bool) realpath($this->storage()->path($this->path));
    }

    /** @return string */
    public function dir()
    {
        return pathinfo($this->path, PATHINFO_DIRNAME);
    }

    /** @return string */
    public function name()
    {
        return pathinfo($this->path, PATHINFO_BASENAME);
    }

    /** @return string */
    public function ext()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /** @return integer */
    public function size()
    {
        return $this->storage()->size($this->path);
    }

    /** @return resource */
    public function stream()
    {
        return $this->storage()->readStream($this->path);
    }

    /** @return StreamedResponse */
    public function response($filename = null, $headers = [])
    {
        return $this->storage()->response($this->path, $filename, $headers);
    }

    /** @return string|false */
    public function mime()
    {
        return $this->storage()->mimeType($this->path);
    }

    /** @return string|null */
    public function url($expiration = null)
    {
        try {
            if ($expiration) {
                return $this->storage()->temporaryUrl($this->path, $expiration);
            } else {
                return $this->storage()->url($this->path);
            }
        } catch (RuntimeException $exception) {
            return null;
        }
    }

    /** @return FilesystemAdapter */
    public function storage()
    {
        return Storage::disk($this->disk);
    }
}
