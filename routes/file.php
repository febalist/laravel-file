<?php

Route::group([
    'namespace' => 'Febalist\Laravel\File',
    'prefix' => 'file',
], function () {
    Route::get('download/{disk}/{path}', 'FileController@download')
        ->where('path', '(.*)')
        ->name('file.download');
    Route::get('gallery/{uuid}', 'FileController@gallery')
        ->name('file.gallery');
    Route::get('zip/{uuid}/{name}', 'FileController@zip')
        ->name('file.zip');
});
