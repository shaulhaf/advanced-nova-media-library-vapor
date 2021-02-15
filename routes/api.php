<?php

use ShaulHaf\AdvancedNovaMediaLibrary\Http\Controllers\DownloadMediaController;
use ShaulHaf\AdvancedNovaMediaLibrary\Http\Controllers\MediaController;

Route::get('/download/{media}', [DownloadMediaController::class, 'show']);

Route::get('/media', [MediaController::class, 'index']);
