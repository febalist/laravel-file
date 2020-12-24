<?php

use Illuminate\Support\Facades\Route;

Route::get('view/{disk}/{path}', 'StreamController@view')
    ->where('path', '(.*)')
    ->name('file.view');

Route::get('download/{disk}/{path}', 'StreamController@download')
    ->where('path', '(.*)')
    ->name('file.download');
