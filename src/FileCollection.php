<?php

namespace Febalist\Laravel\File;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class FileCollection extends Collection
{
    /** @var File[] */
    protected $items = [];

    public function store()
    {
        $this->each->store();

        return $this;
    }

    public function upload()
    {
        $this->each->upload();

        return $this;
    }

    public function delete()
    {
        $this->each->delete();

        return $this;
    }

    public function exists(): bool
    {
        return $this->every->exists();
    }

    public function download($name = 'files')
    {
        $name = str_finish($name, '.zip');
        $options = new Archive();
        $options->setSendHttpHeaders(true);
        $zip = new ZipStream($name, $options);
        foreach ($this->items as $file) {
            $zip->addFileFromStream($file->name(), $file->stream());
        }
        $zip->finish();
    }

    public function gallery(): View
    {
        return view('file::gallery', [
            'files' => $this->items,
        ]);
    }
}
