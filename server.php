<?php

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;

require_once __DIR__.'/vendor/autoload.php';

if (! headers_sent()) {
    header_remove('X-Powered-By');
}

$headers = ['X-Content-Type-Options' => 'nosniff'];
$request = Request::createFromGlobals();
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$publicPath = __DIR__.'/public';
$storagePath = realpath(__DIR__.'/storage/app/public');
$isStorage = str_starts_with($uri, '/storage/');

if (str_contains($uri, "\0")) {
    (new Response('', Response::HTTP_BAD_REQUEST, $headers))->send();

    return;
}

if ($uri !== '/index.php' && preg_match('/\.(?:php[0-9]?|phtml|phar|shtml|cgi|pl|py|htaccess)$/i', $uri)) {
    (new Response('', Response::HTTP_FORBIDDEN, $headers))->send();

    return;
}

// Returning false hands static files to PHP's server, which discards router headers.
$file = realpath($publicPath.$uri);

if ($uri !== '/index.php' && $file !== false && is_file($file)) {
    $insidePublic = str_starts_with($file, realpath($publicPath).DIRECTORY_SEPARATOR);
    $insideStorage = $isStorage && $storagePath !== false
        && str_starts_with($file, $storagePath.DIRECTORY_SEPARATOR);

    if (! $insidePublic && ! $insideStorage) {
        (new Response('', Response::HTTP_FORBIDDEN, $headers))->send();

        return;
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeType = $extension === 'ico' ? 'image/x-icon' : (MimeTypes::getDefault()->getMimeTypes($extension)[0]
        ?? 'application/octet-stream');
    $response = new BinaryFileResponse($file, headers: $headers + ['Content-Type' => $mimeType]);

    if ($isStorage) {
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
    }

    if (! $response->isNotModified($request)) {
        $response->prepare($request);
    }

    $response->send();

    return;
}

require_once $publicPath.'/index.php';
