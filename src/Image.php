<?php

namespace Febalist\Laravel\File;

use Spatie\Image\Image as SpatieImage;
use Spatie\Image\Manipulations;

/**
 * @mixin SpatieImage
 * @method self save()
 */
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

    public function fit_crop($width, $height = null)
    {
        return $this->fit(Manipulations::FIT_CROP, $width, $height ?: $width);
    }
}
