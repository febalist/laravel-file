<?php

namespace Febalist\Laravel\File;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Middleware\ValidateSignature;

class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware(ValidateSignature::class)->only(['proxy', 'download']);
    }

    public function view($disk, $path)
    {
        $file = new File($path, $disk);

        try {
            return $file->view(request('name'));
        } catch (FileNotFoundException $exception) {
            abort(404);
        }
    }

    public function download($disk, $path)
    {
        $file = new File($path, $disk);

        try {
            return $file->download(request('name'));
        } catch (FileNotFoundException $exception) {
            abort(404);
        }
    }
}
