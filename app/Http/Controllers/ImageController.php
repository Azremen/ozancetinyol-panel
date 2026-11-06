<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ImageController extends Controller
{
    /**
     * Serve a resized (and optionally WebP) version of a stored image.
     *
     * URL pattern: /img/{width}/{height}?path=...
     * Append format=jpg to force a JPEG suitable for social previews.
     */
    public function resize(Request $request, int $width, int $height): Response
    {
        $path = (string) $request->query('path', '');

        if ($path === '') {
            abort(400, 'Missing path parameter');
        }

        if ($width < 1 || $height < 1) {
            abort(404);
        }

        $width = min($width, 3840);
        $height = min($height, 2160);

        $requestedFormat = $request->query('format');

        if ($requestedFormat !== null && $requestedFormat !== 'jpg') {
            abort(422, 'Unsupported image format');
        }

        $diskPath = ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? '', '/');
        if ($diskPath === '' || in_array('..', explode('/', $diskPath), true) || str_contains($diskPath, "\0")) {
            abort(400, 'Invalid image path');
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($diskPath)) {
                abort(404);
            }

            $sourceAbsolute = $disk->path($diskPath);
            $sourceVersion = hash_file('sha256', $sourceAbsolute);

            if ($sourceVersion === false) {
                throw new \RuntimeException('Unable to fingerprint image source.');
            }
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            abort(500, 'Image storage is unavailable');
        }

        $wantsWebp = $requestedFormat === null
            && ($request->boolean('webp')
                || str_contains(strtolower($request->header('Accept', '')), 'image/webp'));
        $sourceExtension = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION)) ?: 'jpg';
        $outputExtension = $wantsWebp ? 'webp' : ($requestedFormat ?? $sourceExtension);
        $cacheKey = 'cache/img/'.$width.'x'.$height.'/'.$sourceVersion.'/'.$diskPath;

        if ($requestedFormat !== null) {
            $cacheKey = 'cache/img/'.$width.'x'.$height.'/'.$sourceVersion.'/format-'.$requestedFormat.'/'.$diskPath;
        }

        if ($wantsWebp || $requestedFormat !== null) {
            $cacheKey = preg_replace('/\.[^.\/]+$/', '.'.$outputExtension, $cacheKey)
                ?? $cacheKey.'.'.$outputExtension;
        }

        try {
            if (! $disk->exists($cacheKey)) {
                $disk->makeDirectory(dirname($cacheKey));
                $quality = 85;

                do {
                    Image::load($sourceAbsolute)
                        ->fit(Fit::Crop, $width, $height)
                        ->quality($wantsWebp ? 82 : $quality)
                        ->format($outputExtension)
                        ->save($disk->path($cacheKey));

                    $outputSize = filesize($disk->path($cacheKey));
                    if ($requestedFormat !== 'jpg' || ($outputSize !== false && $outputSize < 600 * 1024)) {
                        break;
                    }

                    $quality -= 10;
                } while ($quality >= 45);

                if ($requestedFormat === 'jpg' && ($outputSize === false || $outputSize >= 600 * 1024)) {
                    throw new \RuntimeException('JPEG social preview exceeds the 600 KB size limit.');
                }

                $disk->setVisibility($cacheKey, 'public');
            }
        } catch (Throwable $exception) {
            report($exception);
            abort(500, 'Image could not be processed');
        }

        $mime = match ($outputExtension) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        $response = response()->file($disk->path($cacheKey), [
            'Content-Type' => $mime,
            'Vary' => 'Accept',
        ]);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
