<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CspMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $this->applyHeaders($request, $response);
    }

    public function applyHeaders(Request $request, Response $response): Response
    {
        $localAssets = app()->environment('local') ? ' http://localhost:* http://127.0.0.1:*' : '';
        $localSockets = app()->environment('local') ? ' ws://localhost:* ws://127.0.0.1:*' : '';
        $directives = [
            "default-src 'self'",
            // Filament's templates and Alpine expressions require these script permissions.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://static.cloudflareinsights.com".$localAssets,
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com".$localAssets,
            "img-src 'self' data: blob: https:",
            "font-src 'self' https://cdnjs.cloudflare.com data:".$localAssets,
            "connect-src 'self' https://static.cloudflareinsights.com".$localAssets.$localSockets,
            "worker-src 'self' blob:",
            "frame-ancestors 'none'",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "manifest-src 'self'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $csp = implode('; ', $directives);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->remove('X-Powered-By');
        $cacheControl = $response->headers->get('Cache-Control', '');
        $isCachedImage = str_starts_with($request->path(), 'img/')
            && $response->isSuccessful()
            && preg_match('/(?:^|,\s*)public(?:,|$)/', $cacheControl) === 1
            && str_contains($cacheControl, 'immutable');

        if (! $isCachedImage) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if ($response->isRedirection()) {
            $response->setContent('');
            $response->headers->set('Content-Length', '0');
        }

        return $response;
    }
}
