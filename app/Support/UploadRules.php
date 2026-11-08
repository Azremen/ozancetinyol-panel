<?php

namespace App\Support;

/**
 * Central allow-lists for panel file uploads.
 *
 * Filament's `->image()` accepts `image/*`, which includes `image/svg+xml`.
 * SVG files can carry scripts and are served from the panel origin without a
 * CSP, so every upload must use one of these explicit raster-only lists.
 */
final class UploadRules
{
    /** @var list<string> */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /** @var list<string> */
    public const FAVICON_MIME_TYPES = [
        'image/png',
        'image/x-icon',
        'image/vnd.microsoft.icon',
    ];

    /**
     * Extensions accepted by Livewire's temporary upload endpoint. Kept in
     * sync with the MIME lists above; this endpoint is reachable from the
     * login page, so it must never accept scripts or markup.
     *
     * @var list<string>
     */
    public const TEMPORARY_UPLOAD_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'ico',
    ];

    public const MAX_IMAGE_KILOBYTES = 10240;

    public const MAX_FAVICON_KILOBYTES = 512;

    /**
     * @return list<string>
     */
    public static function temporaryUploadRules(): array
    {
        return [
            'required',
            'file',
            'mimes:'.implode(',', self::TEMPORARY_UPLOAD_EXTENSIONS),
            'max:'.self::MAX_IMAGE_KILOBYTES,
        ];
    }
}
