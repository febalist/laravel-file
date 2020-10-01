<?php

namespace Febalist\Laravel\File;

use Carbon\Carbon;
use Febalist\Laravel\File\Exceptions\CannotSaveFileException;
use File as FileHelper;
use Illuminate\Http\File as IlluminateFile;
use Illuminate\Support\Collection;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use Mimey\MimeTypes;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class File extends StoragePath
{
    use InteractsWithTime;

    const TEMP_DISK = 'local';
    const TEMP_DIR = 'temp';

    const ICONS = [
        '3g2',
        '3gp',
        'ai',
        'air',
        'asf',
        'avi',
        'bib',
        'cls',
        'csv',
        'deb',
        'djvu',
        'dmg',
        'doc',
        'docx',
        'dwf',
        'dwg',
        'eps',
        'epub',
        'exe',
        'f',
        'f77',
        'f90',
        'flac',
        'flv',
        'gif',
        'gz',
        'ico',
        'indd',
        'iso',
        'jpg',
        'jpeg',
        'key',
        'log',
        'm4a',
        'm4v',
        'midi',
        'mkv',
        'mov',
        'mp3',
        'mp4',
        'mpeg',
        'mpg',
        'msi',
        'odp',
        'ods',
        'odt',
        'oga',
        'ogg',
        'ogv',
        'pdf',
        'png',
        'pps',
        'ppsx',
        'ppt',
        'pptx',
        'psd',
        'pub',
        'py',
        'qt',
        'ra',
        'ram',
        'rar',
        'rm',
        'rpm',
        'rtf',
        'rv',
        'skp',
        'spx',
        'sql',
        'sty',
        'tar',
        'tex',
        'tgz',
        'tiff',
        'ttf',
        'txt',
        'vob',
        'wav',
        'wmv',
        'xls',
        'xlsx',
        'xml',
        'xpi',
        'zip',
    ];

    protected $name;

    public function __construct($path, $disk, $name = null)
    {
        $this->path = static::pathJoin($path);
        $this->disk = static::diskName($disk);
        $this->name = $name ?? basename($this->path);
    }

    /** @return static */
    public static function temp($extension = null, $exists = true)
    {
        $path = static::tempPath($extension);

        if ($exists) {
            return static::create(null, $path, static::TEMP_DISK);
        } else {
            return static::load($path, static::TEMP_DISK);
        }
    }

    /** @return string */
    public static function galleryUrl($files)
    {
        if (!$files instanceof Collection) {
            $files = collect(array_wrap($files));
        }

        $uuid = (string) Str::uuid();
        cache([
            "febalist.file:gallery:$uuid" => $files->map(function (File $file) {
                return [$file->path, $file->disk, $file->name(true)];
            })->toArray(),
        ], 1);

        return route('file.gallery', $uuid);
    }

    public static function zip($files, $name = 'files.zip')
    {
        if (!$files instanceof Collection) {
            $files = collect(array_wrap($files));
        }

        $name = str_finish($name, '.zip');
        $options = new Archive();
        $options->setSendHttpHeaders(true);
        $zip = new ZipStream($name, $options);
        $files->each(function (File $file) use ($zip) {
            $zip->addFileFromStream($file->name(true), $file->stream());
        });
        $zip->finish();
    }

    public static function zipUrl($files, $name = 'files.zip')
    {
        if (!$files instanceof Collection) {
            $files = collect(array_wrap($files));
        }

        $uuid = (string) Str::uuid();
        cache([
            "febalist.file:zip:$uuid" => $files->map(function (File $file) {
                return [$file->path, $file->disk, $file->name(true)];
            })->toArray(),
        ], 5);

        return route('file.zip', [$uuid, $name]);
    }

    /** @return static */
    public static function create($contents, $path, $disk = 'default')
    {
        $file = new static($path, $disk);
        $file->write($contents);

        return $file;
    }

    /** @return static */
    public static function createTemp($contents, $extension = null)
    {
        $path = static::tempPath($extension);

        return static::create($contents, $path, static::TEMP_DISK);
    }

    /** @return static */
    public static function put($source, $path, $disk = 'default', $delete = false)
    {
        if (is_string($source)) {
            if (starts_with($source, ['http://', 'https://'])) {
                $source = static::resource($source);
            } else {
                $source = new IlluminateFile($source);
            }
        }

        $file = static::create($source, $path, $disk);

        if ($delete && $source instanceof SymfonyFile) {
            FileHelper::delete($source);
        }

        return $file;
    }

    /** @return static */
    public static function putTemp($source, $delete = false)
    {
        $path = static::tempPath(static::pathExtension(static::fileName($source)));

        return static::put($source, $path, static::TEMP_DISK, $delete);
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

    public static function pathDirectory($path)
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);

        return $dir == '.' ? '' : $dir;
    }

    public static function fileName($file, $slug = false)
    {
        if (is_string($file) && starts_with($file, ['http://', 'https://'])) {
            $file = static::resource($file);
        }

        $name = null;

        if ($file instanceof File) {
            $name = $file->name(true);
        } elseif (is_resource($file)) {
            $meta = stream_get_meta_data($file);
            if ($meta['wrapper_data'] ?? null) {
                $headers = [];
                foreach ($meta['wrapper_data'] as $data) {
                    $headers[str_before($data, ':')] = str_after($data, ':');
                }

                if ($disposition = ($headers['Content-Disposition'] ?? null)) {
                    $name = str_between($disposition, 'filename="', '"');
                } elseif ($mime = ($headers['Content-Type'] ?? null)) {
                    $extension = static::mimeExtension($mime);
                    if ($extension) {
                        $name = static::pathFilename($meta['uri'] ?? 'file').'.'.$extension;
                    }
                }
            }
            $name = $name ?: basename(stream_get_meta_data($file)['uri'] ?? '') ?: '_';
        } elseif (is_string($file)) {
            $name = basename($file);
        } elseif ($file instanceof UploadedFile) {
            $name = $file->getClientOriginalName() ?: $file->getFilename();
        } elseif ($file instanceof SymfonyFile) {
            $name = $file->getFilename();
        } else {
            throw new RuntimeException('Invalid file');
        }

        $name = str_before($name, '?');

        return $slug ? static::slugName($name) : $name;
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

    public static function pathFilename($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function pathExtension($path)
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function slugName($filename)
    {
        $name = str_slug(static::pathFilename($filename), '_') ?: '_';
        $extension = strtolower(static::pathExtension($filename));

        return $name.($extension ? ".$extension" : '');
    }

    public static function tempName($extension = null)
    {
        $uuid = (string) Str::orderedUuid();
        $extension = $extension ?: 'tmp';

        return "$uuid.$extension";
    }

    public static function tempDirectory($absolute = false)
    {
        $name = static::TEMP_DIR;
        $path = storage_path("app/$name");
        if (!FileHelper::exists($path)) {
            FileHelper::makeDirectory($path);
        }

        return $absolute ? $path : $name;
    }

    public static function tempPath($extension = null, $absolute = false)
    {
        $directory = static::tempDirectory($absolute);
        $name = static::tempName($extension);
        $path = static::pathJoin($directory, $name);

        return $absolute ? str_start($path, '/') : $path;
    }

    public static function diskName($name)
    {
        if (in_array($name, ['default', 'cloud'])) {
            return config("filesystems.$name");
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

    /** @return resource */
    public static function resource($url)
    {
        return fopen($url, 'rb', false, stream_context_create([
            'ssl' => [
                'verify_peer' => false,
            ],
        ]));
    }

    protected static function mimey()
    {
        return new MimeTypes();
    }

    /** @return static|null */
    public function neighbor($path, $check = false)
    {
        return static::load([$this->directory(), $path], $this->disk, $check);
    }

    /** @return static */
    public function move($path, $disk = null)
    {
        if (func_num_args() == 1 && $path instanceof File) {
            $target = $path;
        } else {
            $target = static::load($path, $disk ?? $this->disk);
        }

        if ($target->disk == $this->disk && $target->path == $this->path) {
            return $this;
        }

        if ($target->exists()) {
            $target->delete();
        }

        if ($target->disk == $this->disk) {
            $target->dir()->create();

            $this->storage()->move($this->path, $target->path);

            $target->checkExists();
        } else {
            $this->copy($target);
            $this->delete();
        }

        $this->path = $target->path;
        $this->disk = $target->disk;

        return $this;
    }

    /** @return static */
    public function copy($path, $disk = null)
    {
        if (func_num_args() == 1 && $path instanceof File) {
            $target = $path;
        } else {
            $target = static::load($path, $disk ?? $this->disk);
        }

        if ($target->disk == $this->disk && $target->path == $this->path) {
            return $this;
        }

        if ($target->exists()) {
            $target->delete();
        }

        if ($target->disk == $this->disk) {
            $target->dir()->create();

            $this->storage()->copy($this->path, $target->path);

            $target->checkExists();
        } else {
            $target->write($this);
        }

        return $target;
    }

    /** @return static */
    public function copyTemp()
    {
        $temp = static::temp($this->extension(), false);

        return $this->copy($temp);
    }

    public function delete()
    {
        $this->storage()->delete($this->path);

        return $this;
    }

    /** @return static */
    public function rename($name)
    {
        return $this->move([$this->directory(), $name]);
    }

    /** @return string|bool */
    public function local()
    {
        if (config("filesystems.disks.$this->disk.driver") != 'local') {
            return false;
        }

        return $this->storage()->path($this->path);
    }

    /** @return string */
    public function directory()
    {
        return static::pathDirectory($this->path);
    }

    /** @return string */
    public function name($original = false)
    {
        return $original ? $this->name : basename($this->path);
    }

    /** @return string */
    public function extension()
    {
        return static::pathExtension($this->name(true));
    }

    /** @return integer */
    public function size()
    {
        return $this->storage()->size($this->path);
    }

    /** @return resource */
    public function stream($send = false)
    {
        $resource = $this->storage()->readStream($this->path);

        if ($send) {
            fpassthru($resource);
        }

        return $resource;
    }

    /** @return StreamedResponse */
    public function response($filename = null, $headers = [], $download = false)
    {
        $filename = $filename ?: $this->name(true);
        $disposition = $download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE;
        $headers = [
            'Content-Type' => $this->mime(),
            'Content-Length' => $this->size(),
            'Content-Disposition' => "$disposition; filename=\"$filename\"",
        ];

        $callback = function () {
            return $this->stream(true);
        };

        return response()->stream($callback, 200, $headers);
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

    /** @return string */
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

        return $this->streamUrl($expiration);
    }

    public function streamUrl($expiration = null, $name = null)
    {
        return route_signed('file.stream', [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $name ?: $this->name(true),
        ], $expiration);
    }

    public function downloadUrl($expiration = null, $name = null)
    {
        return route_signed('file.download', [
            'disk' => $this->disk,
            'path' => $this->path,
            'name' => $name ?: $this->name(true),
        ], $expiration);
    }

    /** @return string */
    public function viewUrl($expiration = null, $name = null)
    {
        $url = urlencode($this->streamUrl($expiration));
        $name = urlencode($name ?: $this->name(true));

        return "https://febalist.github.io/viewer/?url=$url&name=$name";
    }

    public function iconUrl($size = 128)
    {
        $extension = $this->extension();
        if (in_array($extension, static::ICONS)) {
            return "https://raw.githubusercontent.com/eagerterrier/MimeTypes-Link-Icons/master/images/$extension-icon-{$size}x{$size}.png";
        }

        return "https://img.icons8.com/material/$size/888888/file.png";
    }

    /** @return Image|null */
    public function image()
    {
        if ($this->convertible()) {
            return new Image($this);
        }

        return null;
    }

    /** @return string */
    public function sha1()
    {
        return sha1_file($this->local() ?: $this->url()) ?: null;
    }

    public function read()
    {
        return $this->storage()->read($this->path);
    }

    public function write($contents)
    {
        if (is_callable($contents)) {
            $temp = static::temp(null, false);
            $contents($temp->local());
            $temp->move($this);

            return $this;
        } else {
            if ($contents instanceof File) {
                $contents = $contents->stream();
            } elseif ($contents instanceof SymfonyFile) {
                $contents = fopen($contents, 'r');
            }

            $this->dir()->create();

            if (is_resource($contents)) {
                $this->storage()->putStream($this->path, $contents);
            } else {
                $this->storage()->put($this->path, $contents);
            }

            $this->checkExists();
        }

        return $this;
    }

    public function fetch(callable $callback, $update = false)
    {
        $copy = $this->copyTemp();

        $result = $callback($copy->local());

        if ($update) {
            $copy->move($this);
        }

        if ($copy->exists()) {
            $copy->delete();
        }

        return $result;
    }

    public function transform(callable $callback)
    {
        $this->fetch($callback, true);

        return $this;
    }

    protected function checkExists()
    {
        throw_unless($this->exists(), CannotSaveFileException::class);
    }

    /** @deprecated */
    protected function dir($check = false)
    {
        return Directory::load(static::pathDirectory($this->path), $this->disk, $check);
    }
}
