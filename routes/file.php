<?php

Route::group([
    'namespace' => 'Febalist\Laravel\File',
    'prefix' => 'file',
], function () {
    Route::get('{disk}/{path}', 'FileController@download')->name('file.download');
});
