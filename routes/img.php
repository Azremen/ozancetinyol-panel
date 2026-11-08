<?php

/**
 * Image resize routes.
 *
 * Registered without a prefix so the route works from the configured panel URL.
 */

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/img/{width}/{height}', [ImageController::class, 'resize'])
    ->middleware('throttle:300,1')
    ->where('width', '[0-9]+')
    ->where('height', '[0-9]+')
    ->name('img.resize');
