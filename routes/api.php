<?php

use ShaulHaf\AdvancedNovaMediaLibrary\Http\Controllers\DownloadMediaController;
use ShaulHaf\AdvancedNovaMediaLibrary\Http\Controllers\MediaController;
use ShaulHaf\AdvancedNovaMediaLibrary\Http\Controllers\SignedStorageUrlController;

Route::get('/download/{media}', [DownloadMediaController::class, 'show']);

Route::get('/media', [MediaController::class, 'index']);

Route::post('createImage', [SignedStorageUrlController::class, 'createImage']);
Route::post('signed-storage-url', [SignedStorageUrlController::class, 'store']);
