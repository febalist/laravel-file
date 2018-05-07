<?php

namespace Febalist\Laravel\File;

use Carbon\Carbon;
use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File as IlluminateFile;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use RuntimeException;
use Storage;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property-read boolean           $exists
 * @property-read string|false      $local
 * @property-read string            $directory
 * @property-read string            $name
 * @property-read string            $extension
 * @property-read integer           $size
 * @property-read string            $mime
 * @property-read string            $url
 * @property-read FilesystemAdapter $storage
 * @property-read Image             $image
 */
class File
{
    use InteractsWithTime;

    public $path;
    public $disk;

    public function __construct($path, $disk)
    {
        $this->path = static::pathJoin($path);
        $this->disk = static::diskName($disk);
    }

    /** @return static|null */
    public static function load($path, $disk = 'default', $check = false)
    {
        $file = new static($path, $disk);

        if ($check && !$file->exists()) {
            return null;
        }

        return $file;
    }

    /** @return static */
    public static function put($file, $path, $disk = 'default')
    {
        $path = static::pathJoin($path);
        $disk = static::diskName($disk);

        if ($file instanceof File) {
            $file = $file->stream();
        } elseif (is_string($file) && starts_with($file, ['http://', 'https://'])) {
            $file = fopen($file, 'rb');
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

    public static function pathJoin(...$path)
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

    public static function getName($file)
    {
        if ($file instanceof File) {
            return $file->name;
        } elseif (is_resource($file)) {
            return basename(stream_get_meta_data($file)['uri'] ?? '') ?: '_';
        } elseif (is_string($file)) {
            return basename($file);
        } elseif ($file instanceof UploadedFile) {
            return $file->getClientOriginalName() ?: $file->getFilename();
        } elseif ($file instanceof SymfonyFile) {
            return $file->getFilename();
        } else {
            throw new RuntimeException('Invalid file');
        }
    }

    public static function slugName($filename)
    {
        $name = str_slug(pathinfo($filename, PATHINFO_FILENAME), '_') ?: '_';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return $name.($extension ? ".$extension" : '');
    }

    public static function tempName($extension = null)
    {
        $uuid = (string) Str::orderedUuid();
        $extension = $extension ?: 'tmp';

        return "$uuid.$extension";
    }

    protected static function putFile(SymfonyFile $file, $path, $disk)
    {
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
    }

    protected static function putStream($resource, $path, $disk)
    {
        Storage::disk($disk)->putStream($path, $resource);
    }

    protected static function diskName($name)
    {
        if ($name == 'default') {
            return config('filesystems.default');
        } elseif ($name == 'cloud') {
            return config('filesystems.cloud');
        }

        return $name;
    }

    public function __get($name)
    {
        if (in_array($name, [
            'exists',
            'local',
            'directory',
            'name',
            'extension',
            'size',
            'mime',
            'url',
            'storage',
            'image',
        ])) {
            return $this->$name();
        }

        throw new Exception("Undefined property: $name");
    }

    /** @return boolean */
    public function exists()
    {
        return $this->storage()->exists($this->path);
    }

    /** @return static|null */
    public function neighbor($path, $check = false)
    {
        return static::load([$this->directory, $path], $this->disk, $check);
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
        $path = static::pathJoin($path);
        $disk = static::diskName($disk ?: $this->disk);

        if ($disk == $this->disk) {
            $this->storage->move($this->path, $path);
        } else {
            $this->copy($path, $disk);
            $this->delete();
        }

        $this->path = $path;
        $this->disk = $disk;

        return $this;
    }

    /** @return static */
    public function rename($name)
    {
        $this->move([$this->directory, $name]);

        return $this;
    }

    /** @return static */
    public function cloud($path = null)
    {
        return $this->move($path ?: $this->path, 'cloud');
    }

    /** @return string */
    public function local()
    {
        return realpath($this->storage->path($this->path));
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
        return $this->storage->size($this->path);
    }

    /** @return resource */
    public function stream()
    {
        return $this->storage->readStream($this->path);
    }

    /** @return StreamedResponse */
    public function response($filename = null, $headers = [])
    {
        return $this->storage->response($this->path, $filename, $headers);
    }

    /** @return string|false */
    public function mime()
    {
        return $this->storage->mimeType($this->path);
    }

    /** @return string|null */
    public function url($expiration = null)
    {
        try {
            if ($expiration) {
                $expiration = $this->availableAt($expiration);
                $expiration = Carbon::createFromTimestamp($expiration);

                return $this->storage->temporaryUrl($this->path, $expiration);
            } else {
                return $this->storage->url($this->path);
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
