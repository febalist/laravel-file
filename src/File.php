<?php

namespace Febalist\Laravel\File;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class File
{
    public const ROOT_DISK = 'root';

    protected const TEMP_DIRECTORY = 'febalist-laravel-file';

    protected $path;
    protected $disk;

    public function __construct($path, $disk)
    {
        $this->path = $path;
        $this->disk = $disk;
    }

    /** @return static */
    public static function create($contents, $path, $disk = null)
    {
        $file = new static($path, $disk ?? Config::get('filesystems.default'));
        $file->write($contents);

        return $file;
    }

    /** @return static */
    public static function temp($contents = null, $name = null)
    {
        $storage = Storage::disk(static::ROOT_DISK);

        $name = $name ?? 'file.tmp';
        do {
            $directory = sys_get_temp_dir().'/'.static::TEMP_DIRECTORY.'/'.uniqid('', true);
        } while ($storage->exists($directory));
        if (!$storage->makeDirectory($directory)) {
            throw new RuntimeException('Can not create temp directory');
        }

        $file = new static("$directory/$name", static::ROOT_DISK);

        if ($contents !== null) {
            $file->write($contents);
        }

        return $file;
    }

    /** @return static */
    public static function load($source)
    {
        if ($source instanceof self) {
            return new static($source->path, $source->disk);
        }

        if (Tools::isUrl($source)) {
            $source = Tools::resource($source);
        } elseif ($source instanceof SplFileInfo) {
            $source = $source->getPathname();
        }

        if (is_resource($source)) {
            return static::temp($source, Tools::name($source));
        } elseif (!is_string($source)) {
            throw new RuntimeException('Invalid source');
        }

        return new static($source, static::ROOT_DISK);
    }

    /** @return static */
    public static function put($source, $path, $disk = null)
    {
        $file = static::load($source);

        return $file->copy($path, $disk ?? Config::get('filesystems.default'));
    }

    /** @return Collection|static[] */
    public static function request($key = null)
    {
        $files = [];

        foreach (Arr::wrap(Request::file($key)) as $file) {
            $files[$file->getClientOriginalName()] = static::load($file);
        }

        return collect($files);
    }

    /** @return string */
    public function disk()
    {
        return $this->disk;
    }

    /** @return string */
    public function path()
    {
        return $this->path;
    }

    /** @return string */
    public function directory()
    {
        $dir = pathinfo($this->path, PATHINFO_DIRNAME);

        return $dir === '.' ? '' : $dir;
    }

    /** @return string */
    public function name()
    {
        return basename($this->path);
    }

    /** @return string */
    public function extension()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /** @return \Illuminate\Filesystem\FilesystemAdapter */
    public function storage()
    {
        return Storage::disk($this->disk);
    }

    /** @return boolean */
    public function exists()
    {
        return $this->storage()->exists($this->path);
    }

    /** @return int */
    public function timestamp()
    {
        return $this->storage()->getTimestamp($this->path);
    }

    /** @return \Illuminate\Support\Carbon */
    public function time()
    {
        return Date::createFromTimestamp($this->timestamp());
    }

    /** @return $this */
    public function delete()
    {
        $this->storage()->delete($this->path);

        return $this;
    }

    /** @return static */
    public function copy($path = null, $disk = null)
    {
        return $this->transfer($path, $disk, false);
    }

    /** @return $this */
    public function move($path = null, $disk = null)
    {
        return $this->transfer($path, $disk, true);
    }

    /** @return $this */
    public function rename($name)
    {
        return $this->move($this->directory().'/'.$name);
    }

    /** @return $this */
    public function store($path = null)
    {
        return $this->move($path, Config::get('filesystems.default'));
    }

    /** @return $this */
    public function upload($path = null)
    {
        return $this->move($path, Config::get('filesystems.cloud'));
    }

    /** @return $this */
    public function write($contents)
    {
        if ($contents instanceof self) {
            $contents = $contents->stream();
        } elseif ($contents instanceof SplFileInfo) {
            $contents = fopen($contents, 'rb');
        }

        $this->mkdir();

        if (is_resource($contents)) {
            $this->storage()->putStream($this->path, $contents);
        } else {
            $this->storage()->put($this->path, $contents);
        }

        if (!$this->exists()) {
            throw new FileNotFoundException("$this->disk:$this->path");
        }

        return $this;
    }

    /** @return resource */
    public function stream()
    {
        return $this->storage()->readStream($this->path);
    }

    /** @return void */
    public function send()
    {
        fpassthru($this->stream());
    }

    /** @return StreamedResponse */
    public function response($filename = null, $headers = [], $download = false)
    {
        $filename = $filename ?: $this->name();
        $disposition = $download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE;

        return Response::stream(function () {
            $this->send();
        }, 200, array_merge([
            'Content-Type' => $this->mime(),
            'Content-Length' => $this->size(),
            'Content-Disposition' => "$disposition; filename=\"$filename\"",
            'X-Frame-Options' => null,
        ], $headers));
    }

    /** @return StreamedResponse */
    public function download($filename = null, $headers = [])
    {
        return $this->response($filename, $headers, true);
    }

    /** @return int */
    public function size()
    {
        return $this->storage()->size($this->path);
    }

    /** @return string */
    public function mime()
    {
        return $this->storage()->mimeType($this->path);
    }

    /** @return string */
    public function read()
    {
        return $this->storage()->read($this->path);
    }

    /** @return string */
    public function url($expiration = null)
    {
        try {
            if ($expiration) {
                $url = $this->storage()->temporaryUrl($this->path, $expiration);
            } else {
                $url = $this->storage()->url($this->path);
            }
        } catch (RuntimeException $exception) {
            $url = null;
        }

        if (Tools::isUrl($url)) {
            return $url;
        }

        return $this->streamUrl($expiration);
    }

    /** @return string */
    public function streamUrl($expiration = null, $name = null)
    {
        return URL::signedRoute('file.stream', [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $name ?? $this->name(),
        ], $expiration);
    }

    /** @return string */
    public function downloadUrl($expiration = null, $name = null)
    {
        return URL::signedRoute('file.download', [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $name ?? $this->name(),
        ], $expiration);
    }

    /** @return static */
    protected function transfer($path, $disk, $delete)
    {
        if (func_num_args() === 1 && $path instanceof self) {
            $target = $path;
        } else {
            $target = new static($path ?? $this->path, $disk ?? $this->disk);
        }

        if ($target->disk === $this->disk && $target->path === $this->path) {
            return $this;
        }

        if ($target->exists()) {
            $target->delete();
        }

        if ($target->disk === $this->disk) {
            $target->mkdir();

            if ($delete) {
                $this->storage()->move($this->path, $target->path);
            } else {
                $this->storage()->copy($this->path, $target->path);
            }

            if (!$this->exists()) {
                throw new FileNotFoundException("$target->disk:$target->path");
            }
        } else {
            $target->write($this);

            if ($delete) {
                $this->delete();
            }
        }

        if ($delete) {
            $this->path = $target->path;
            $this->disk = $target->disk;

            return $this;
        } else {
            return $target;
        }
    }

    protected function mkdir()
    {
        if ($dir = $this->directory()) {
            $this->storage()->createDir($dir);
        }
    }
}
