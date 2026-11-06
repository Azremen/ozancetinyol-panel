<?php

use Illuminate\Support\Facades\Route;

// Prevent search engine indexing of the admin panel
Route::get('/robots.txt', function () {
    return response(
        "User-agent: *\nDisallow: /\n",
        200
    )->header('Content-Type', 'text/plain');
});
