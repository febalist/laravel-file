<?php

use Illuminate\Support\Facades\Route;

Route::get('stream/{disk}/{path}', 'StreamController@stream')
    ->where('path', '(.*)')
    ->name('file.stream');

Route::get('download/{disk}/{path}', 'StreamController@download')
    ->where('path', '(.*)')
    ->name('file.download');
