<?php

namespace Febalist\Laravel\File;

use File as IlluminateFile;
use Illuminate\Support\Str;
use Spatie\Image\Image as SpatieImage;
use Spatie\Image\Manipulations;

/** @mixin SpatieImage */
class Image
{
    public $file;
    protected $image;

    public function __construct(File $file)
    {
        $this->file = $file;
        $this->image = SpatieImage::load($file->local());
    }

    public function __call($method, $arguments)
    {
        $output = $this->image->$method(...$arguments);

        if (starts_with($method, 'get')) {
            return $output;
        }

        return $this;
    }

    public function save($path = null, $disk = null)
    {
        if ($path || $disk) {
            $temp = storage_path(Str::uuid());

            $this->image->save($temp);

            $file = File::put($temp, $path ?: $this->file->path, $disk ?: $this->file->disk);

            IlluminateFile::delete($temp);

            return $file;
        } else {
            $this->image->save();

            return $this->file;
        }
    }

    public function fit_crop($width, $height)
    {
        return $this->fit(Manipulations::FIT_CROP, $width, $height);
    }
}
