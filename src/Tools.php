<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

class Tools
{
    /** @return resource */
    public static function resource($url)
    {
        return fopen($url, 'rb', false, stream_context_create([
            'ssl' => [
                'verify_peer' => false,
            ],
        ]));
    }

    /** @return string */
    public static function name($source)
    {
        if ($source instanceof File) {
            return $source->name();
        }

        if (static::isUrl($source)) {
            $source = static::resource($source);
        }

        if (is_resource($source)) {
            $name = null;
            $meta = stream_get_meta_data($source);
            if ($meta['wrapper_data'] ?? null) {
                $headers = [];
                foreach ($meta['wrapper_data'] as $data) {
                    $headers[Str::before($data, ':')] = Str::after($data, ':');
                }

                if ($disposition = ($headers['Content-Disposition'] ?? null)) {
                    $name = Str::between($disposition, 'filename="', '"');
                } elseif ($mime = ($headers['Content-Type'] ?? null)) {
                    $extension = (new MimeTypes)->getExtensions($mime)[0] ?? null;
                    if ($extension) {
                        $name = pathinfo($meta['uri'] ?? 'file', PATHINFO_FILENAME).'.'.$extension;
                    }
                }
            }
            $name = $name ?: basename(stream_get_meta_data($source)['uri'] ?? '') ?: '_';
        } elseif ($source instanceof UploadedFile) {
            $name = $source->getClientOriginalName() ?: $source->getFilename();
        } elseif ($source instanceof SplFileInfo) {
            $name = $source->getFilename();
        } elseif (is_string($source)) {
            $name = basename($source);
        } else {
            throw new RuntimeException('Invalid source');
        }

        return Str::before($name, '?');
    }

    public static function isUrl($string)
    {
        return is_string($string) && Str::startsWith($string, ['http://', 'https://']);
    }

    public static function makeTempDirectory()
    {
        $storage = Storage::disk(File::ROOT_DISK);

        do {
            $directory = sys_get_temp_dir().'/laravel/'.uniqid('', true);
        } while ($storage->exists($directory));

        if (!$storage->makeDirectory($directory)) {
            throw new RuntimeException('Cannot create temp directory');
        }

        return $directory;
    }
}
