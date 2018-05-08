<?php

namespace Febalist\Laravel\File;

use Carbon\Carbon;
use Exception;
use File as FileHelper;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File as IlluminateFile;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use Mimey\MimeTypes;
use RuntimeException;
use Storage;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use URL;

/**
 * @property-read boolean           $exists
 * @property-read string|false      $local
 * @property-read string            $directory
 * @property-read string            $name
 * @property-read string            $extension
 * @property-read integer           $size
 * @property-read string            $mime
 * @property-read string            $type
 * @property-read boolean           $convertible
 * @property-read string            $url
 * @property-read string            $preview
 * @property-read string            $embedded
 * @property-read FilesystemAdapter $storage
 * @property-read Image|null        $image
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

    public static function fileName($file)
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

    public static function fileMime($file)
    {
        if ($file instanceof UploadedFile) {
            return $file->getClientMimeType() ?: $file->getMimeType();
        } elseif ($file instanceof SymfonyFile) {
            return $file->getMimeType();
        }

        $name = static::fileName($file);
        $extension = static::pathExtension($name);

        return static::extensionMime($extension);
    }

    public static function pathExtension($path)
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
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

    public static function diskName($name)
    {
        if ($name == 'default') {
            return config('filesystems.default');
        } elseif ($name == 'cloud') {
            return config('filesystems.cloud');
        }

        return $name;
    }

    public static function extensionMime($extension)
    {
        return static::mimey()->getMimeType($extension);
    }

    public static function mimeExtension($mime)
    {
        return static::mimey()->getExtension($mime);
    }

    protected static function putFile(SymfonyFile $file, $path, $disk)
    {
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
    }

    protected static function putStream($resource, $path, $disk)
    {
        Storage::disk($disk)->putStream($path, $resource);
    }

    protected static function mimey()
    {
        return new MimeTypes();
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
            'type',
            'convertible',
            'url',
            'preview',
            'embedded',
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
        return static::load([$this->directory(), $path], $this->disk, $check);
    }

    /** @return static */
    public function copy($path, $disk = null)
    {
        return static::put($this, $path, $disk ?: $this->disk);
    }

    /** @return static */
    public function copyTemp()
    {
        $directory = storage_path('app/temp');
        if (!FileHelper::exists($directory)) {
            FileHelper::makeDirectory($directory);
        }

        $name = static::tempName($this->extension());
        $path = static::pathJoin($directory, $name);

        return static::put($this, $path, 'local');
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

        if ($disk == $this->disk && $path == $this->path) {
            return $this;
        }

        if ($file = static::load($path, $disk, true)) {
            $file->delete();
        }

        if ($disk == $this->disk) {
            $this->storage()->move($this->path, $path);
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
       return $this->move([$this->directory(), $name]);
    }

    /** @return static */
    public function cloud($path = null)
    {
        return $this->move($path ?: $this->path, 'cloud');
    }

    /** @return string */
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
        return basename($this->path);
    }

    /** @return string */
    public function extension()
    {
        return static::pathExtension($this->name());
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
        $filename = File::slugName($filename ?: $this->name());

        return $this->storage()->response($this->path, $filename, $headers);
    }

    /** @return string|false */
    public function mime()
    {
        return static::extensionMime($this->extension());
    }

    /** @return string */
    public function type()
    {
        return str_before($this->mime(), '/');
    }

    /** @return boolean */
    public function convertible()
    {
        return $this->type() == 'image' && in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /** @return string|null */
    public function url($expiration = null)
    {
        if ($expiration) {
            $expiration = $this->availableAt($expiration);
            $expiration = Carbon::createFromTimestamp($expiration);
        }

        try {
            if ($expiration) {
                $url = $this->storage()->temporaryUrl($this->path, $expiration);
            } else {
                $url = $this->storage()->url($this->path);
            }
        } catch (RuntimeException $exception) {
            $url = null;
        }

        if ($url && starts_with($url, ['http://', 'https://'])) {
            return $url;
        }

        return URL::signedRoute('file.download', [$this->disk, $this->path], $expiration);
    }

    /** @return string */
    public function preview($embedded = false)
    {
        $extension = $this->extension();
        $name = $this->name();
        $url = $this->url();

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'ico', 'mp3', 'mp4', 'webm', 'txt'])) {
            return $url;
        } elseif (in_array($extension, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx'])) {
            return 'https://view.officeapps.live.com/op/'.($embedded ? 'embed' : 'view').'.aspx?src='.urlencode($url);
        } elseif (in_array($extension, ['ods', 'sxc', 'csv', 'tsv'])) {
            return "https://sheet.zoho.com/sheet/view.do?&name=$name&url=".urlencode($url);
        } else {
            return 'https://docs.google.com/viewer?'.($embedded ? 'embedded=true&' : '').'url='.urlencode($url);
        }
    }

    /** @return string */
    public function embedded()
    {
        return $this->preview(true);
    }

    /** @return FilesystemAdapter */
    public function storage()
    {
        return Storage::disk($this->disk);
    }

    /** @return Image|null */
    public function image()
    {
        if ($this->convertible()) {
            return new Image($this);
        }

        return null;
    }
}
