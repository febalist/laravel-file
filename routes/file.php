<?php

Route::group([
    'namespace' => 'Febalist\Laravel\File',
    'prefix' => 'file',
    'as' => 'file.',
], function () {
    Route::get('download/{disk}/{path}', 'FileController@download')->where('path', '(.*)')
        ->name('download');
    Route::get('stream/{disk}/{path}', 'FileController@stream')->where('path', '(.*)')
        ->name('stream');
    Route::get('gallery/{uuid}', 'FileController@gallery')
        ->name('gallery');
    Route::get('zip/{uuid}/{name}', 'FileController@zip')
        ->name('zip');
});
