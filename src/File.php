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
    public static function load($disk, $path, $check = false)
    {
        $disk = static::diskName($disk);

        $file = new static($disk, $path);

        if ($check && !$file->exists()) {
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
            if ($resource) {
                $name = pathinfo(stream_get_meta_data($file)['uri'] ?? '', PATHINFO_BASENAME);
            }
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
            $path = static::path($dir, $name);
            Storage::disk($disk)->putStream($path, $file);
        } else {
            $path = Storage::disk($disk)->putFileAs($dir, $file, $name);
        }

        return new static($disk, $path);
    }

    /** @return static[] */
    public static function request($keys = null, $disk = null, $dir = '', $name = null)
    {
        $keys = $keys ? array_wrap($keys) : array_keys(request()->allFiles());

        $files = [];

        foreach ($keys as $key) {
            $request_files = array_wrap(request()->file($key));
            foreach ($request_files as $request_file) {
                $files[] = static::put($request_file, $disk, $dir, $name);
            }
        }

        return $files;
    }

    /** @return static[] */
    public static function requestAll($disk = null, $dir = '')
    {
        $files = [];
        foreach (array_keys(request()->allFiles()) as $key) {
            $files[$key] = static::request($key, $disk, $dir);
        }

        return $files;
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

    protected static function path($dir, $name)
    {
        $dir = $dir ? str_finish($dir, '/') : '';

        return $dir.$name;
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
        $name = $name ?: $this->name();
        $path = static::path($dir, $name);

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
        $path = static::path($this->dir(), $name);

        $this->storage()->rename($this->path, $path);

        $this->path = $path;

        return $this;
    }

    /** @return static */
    public function cloud($dir = '', $name = null)
    {
        return $this->move('cloud', $dir, $name);
    }

    /** @return boolean */
    public function local()
    {
        return (bool) realpath($this->storage()->path($this->path));
    }

    /** @return string */
    public function dir()
    {
        $dir = pathinfo($this->path, PATHINFO_DIRNAME);

        return $dir == '.' ? '' : $dir;
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
