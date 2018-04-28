<?php

namespace Febalist\Laravel\File;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File as IlluminateFile;
use RuntimeException;
use Storage;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class File
{
    public $path;
    public $disk;

    public function __construct($path, $disk)
    {
        $this->path = static::join($path);
        $this->disk = $disk;
    }

    /** @return static|null */
    public static function load($path, $disk = 'default')
    {
        $disk = static::disk($disk);

        $file = new static($path, $disk);

        if (!$file->exists()) {
            return null;
        }

        return $file;
    }

    /** @return static */
    public static function put($file, $path, $disk = 'default')
    {
        $disk = static::disk($disk);

        if ($file instanceof File) {
            $file = $file->stream();
        }

        if (is_resource($file)) {
            static::putStream($file, $path, $disk);
        } else {
            if (!$file instanceof SymfonyFile) {
                $file = new IlluminateFile($file);
            }
            static::putFile($file, $path, $disk);
        }

        return new static($path, $disk);
    }

    public static function join(...$path)
    {
        $path = array_flatten($path);

        $paths = [];

        foreach ($path as $arg) {
            if ($arg !== '') {
                $paths[] = $arg;
            }
        }

        $path = preg_replace('#/+#', '/', join('/', $paths));

        foreach (['./', '/'] as $string) {
            if (starts_with($path, $string)) {
                $path = str_replace_first($string, '', $path);
            }
        }

        return $path;
    }

    public static function filename($file)
    {
        if ($file instanceof File) {
            return $file->name();
        } elseif (is_resource($file)) {
            return basename(stream_get_meta_data($file)['uri'] ?? '') ?: '_';
        } elseif ($file instanceof UploadedFile) {
            return $file->getClientOriginalName() ?: $file->getFilename();
        } elseif (!$file instanceof SymfonyFile) {
            $file = new IlluminateFile($file);
        }

        return $file->getFilename();
    }

    protected static function putFile(SymfonyFile $file, $path, $disk)
    {
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
    }

    protected static function putStream($resource, $path, $disk)
    {
        Storage::disk($disk)->putStream($path, $resource);
    }

    protected static function disk($name)
    {
        if ($name == 'default') {
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

    /** @return static|null */
    public function neighbor($path)
    {
        $path = static::join($this->directory(), $path);

        return static::load($path, $this->disk);
    }

    /** @return static */
    public function copy($path, $disk = null)
    {
        return static::put($this, $path, $disk ?: $this->disk);
    }

    public function delete()
    {
        $this->storage()->delete($this->path);
    }

    /** @return static */
    public function move($path, $disk = null)
    {
        $disk = static::disk($disk ?: $this->disk);

        if ($disk == $this->disk) {
            $this->storage()->move($this->path, $path);
        } else {
            $this->copy($path, $disk);
            $this->delete();
        }

        $this->path = static::join($path);
        $this->disk = $disk;

        return $this;
    }

    /** @return static */
    public function rename($name)
    {
        $path = static::join($this->directory(), $name);

        $this->move($path);

        return $this;
    }

    /** @return static */
    public function cloud($path = null)
    {
        return $this->move($path ?: $this->path, 'cloud');
    }

    /** @return boolean */
    public function local()
    {
        return realpath($this->storage()->path($this->path));
    }

    /** @return string */
    public function directory()
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
    public function extension()
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

    /** @return Image */
    public function image()
    {
        return new Image($this);
    }
}
