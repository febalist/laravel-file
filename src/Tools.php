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
    protected const ICONS = [
        'image' => 'file-image',
        'audio' => 'file-audio',
        'video' => 'file-video',
        'application/pdf' => 'file-pdf',
        'application/msword' => 'file-word',
        'application/vnd.ms-word' => 'file-word',
        'application/vnd.oasis.opendocument.text' => 'file-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml' => 'file-word',
        'application/vnd.ms-excel' => 'file-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml' => 'file-excel',
        'application/vnd.oasis.opendocument.spreadsheet' => 'file-excel',
        'application/vnd.ms-powerpoint' => 'file-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml' => 'file-powerpoint',
        'application/vnd.oasis.opendocument.presentation' => 'file-powerpoint',
        'text/plain' => 'file-text',
        'text/html' => 'file-code',
        'application/json' => 'file-code',
        'application/gzip' => 'file-archive',
        'application/zip' => 'file-archive',
    ];

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
            $directory = sys_get_temp_dir().'/laravel/'.Str::uuid();
        } while ($storage->exists($directory));

        if (!$storage->makeDirectory($directory)) {
            throw new RuntimeException('Cannot create temp directory');
        }

        return $directory;
    }

    public static function mimeIconUrl($mime, $solid = false)
    {
        $name = static::mimeIcon($mime);

        $type = $solid ? 'solid' : 'regular';

        return "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5/svgs/$type/$name.svg";
    }

    public static function mimeIcon($mime)
    {
        foreach (static::ICONS as $prefix => $icon) {
            if (Str::startsWith($mime, $prefix)) {
                return $icon;
            }
        }

        return 'file';
    }
}
