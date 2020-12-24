<?php

use Febalist\Laravel\File\FileController;
use Illuminate\Support\Facades\Route;

Route::get('view/{disk}/{path}', [FileController::class, 'view'])
    ->where('path', '(.*)')
    ->name('file.view');

Route::get('download/{disk}/{path}', [FileController::class, 'download'])
    ->where('path', '(.*)')
    ->name('file.download');
