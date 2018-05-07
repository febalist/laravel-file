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

        return $file->response(File::slugName($file->name));
    }
}
