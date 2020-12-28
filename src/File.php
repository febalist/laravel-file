<?php

namespace Febalist\Laravel\File;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class File
{
    public const ROOT_DISK = 'root';

    protected $path;
    protected $disk;

    public function __construct($path, $disk)
    {
        $this->path = Str::start($path, '/');
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
        $name = $name ?? 'file.tmp';
        $directory = Tools::makeTempDirectory();

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

    /** @return static[] */
    public static function request($key = null)
    {
        $files = [];

        foreach (Arr::flatten(Arr::wrap(Request::file($key))) as $file) {
            $name = $file->getClientOriginalName();
            if (Str::contains($name, ['/', '\\', '?', '%', '*', ':', '|', '"', '<', '>'])) {
                throw new RuntimeException('Invalid filename');
            }
            $files[$name] = static::load($file);
        }

        return $files;
    }

    /** @return FileCollection|static[] */
    public static function receive($key = null)
    {
        $files = static::collect();

        $directory = Tools::makeTempDirectory();

        foreach (static::request($key) as $name => $file) {
            $file->move("$directory/$name");
            $files->push($file);
        }

        return $files;
    }

    /** @return FileCollection|static[] */
    public static function collect($files = [])
    {
        return new FileCollection($files);
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

    /** @return string */
    public function filename()
    {
        return basename($this->path, '.'.$this->extension());
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
    public function type()
    {
        return Str::before($this->mime(), '/');
    }

    /** @return string */
    public function iconUrl($solid = false)
    {
        return Tools::mimeIconUrl($this->mime(), $solid);
    }

    /** @return HtmlString */
    public function icon($class = 'fas')
    {
        $icon = Tools::mimeIcon($this->mime());

        return new HtmlString("<i class='$class fa-$icon'></i>");
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
    public function response($headers = [])
    {
        return Response::stream(function () {
            $this->send();
        }, 200, array_merge([
            'Content-Type' => $this->mime(),
            'Content-Length' => $this->size(),
            'X-Frame-Options' => null,
        ], $headers));
    }

    /** @return StreamedResponse */
    public function view($filename = null, $headers = [])
    {
        $filename = $filename ?: $this->name();
        $disposition = ResponseHeaderBag::DISPOSITION_INLINE;
        $headers['Content-Disposition'] = "$disposition; filename=\"$filename\"";

        return $this->response($headers);
    }

    /** @return StreamedResponse */
    public function download($filename = null, $headers = [])
    {
        $filename = $filename ?: $this->name();
        $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $headers['Content-Disposition'] = "$disposition; filename=\"$filename\"";

        return $this->response($headers);
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

        return $this->viewUrl($expiration);
    }

    /** @return string */
    public function viewUrl($expiration = null, $name = null)
    {
        return URL::signedRoute('file.view', [
            'disk' => $this->disk,
            'path' => substr($this->path, 1),
            'name' => $name ?? $this->name(),
        ], $expiration);
    }

    /** @return string */
    public function downloadUrl($expiration = null, $name = null)
    {
        return URL::signedRoute('file.download', [
            'disk' => $this->disk,
            'path' => substr($this->path, 1),
            'name' => $name ?? $this->name(),
        ], $expiration);
    }

    /** @return string */
    public function viewerUrl($expiration = null, $name = null)
    {
        $url = urlencode($this->url($expiration));
        $name = urlencode($name ?: $this->name());

        return "https://febalist.github.io/viewer/?url=$url&name=$name";
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

            if (!$target->exists()) {
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
