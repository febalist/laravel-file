<?php

namespace Febalist\Laravel\File;

use App\Http\Controllers\Controller;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('signed')->only('download');
    }

    public function download($disk, $path)
    {
        $file = File::load($path, $disk, true);
        abort_unless($file, 404);

        return $file->response(request('name'));
    }

    public function gallery($uuid)
    {
        $files = cache("febalist.file:gallery:$uuid", []);
        abort_unless($files, 404);

        $files = array_map(function ($file) {
            return new File(...$file);
        }, $files);

        return view('file::gallery', compact('files'));
    }

    public function zip($uuid, $name)
    {
        $files = cache("febalist.file:zip:$uuid", []);
        abort_unless($files, 404);

        $files = array_map(function ($file) {
            return new File(...$file);
        }, $files);

        return File::zip($files, $name);
    }
}
