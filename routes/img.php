<?php

/**
 * Image resize routes.
 *
 * Registered without a prefix so the route works from the configured panel URL.
 */

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/img/{width}/{height}', [ImageController::class, 'resize'])
    ->where('width', '[0-9]+')
    ->where('height', '[0-9]+')
    ->name('img.resize');
