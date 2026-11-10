<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Symfony\Component\HttpFoundation\Cookie;

class VerifyCsrfToken extends Middleware
{
    protected function newCookie($request, $config): Cookie
    {
        return parent::newCookie($request, $config)->withHttpOnly(true);
    }
}
