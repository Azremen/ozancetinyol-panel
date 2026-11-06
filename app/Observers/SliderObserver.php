<?php

namespace App\Observers;

use App\Models\Slider;
use Illuminate\Support\Str;

class SliderObserver
{
    /**
     * Handle the Slider "saved" event.
     */
    public function saved(Slider $slider): void
    {
        // When Filament FileUpload stores files, `images` is an array of paths
        // relative to the storage disk (typically public disk). We'll register
        // those files with Spatie medialibrary so conversions run automatically.
        $images = $slider->images;

        if (! is_array($images) || count($images) === 0) {
            return;
        }

        foreach ($images as $path) {
            // skip nulls
            if (empty($path)) {
                continue;
            }

            // derive storage absolute path (public disk)
            $storagePath = storage_path('app/public/'.ltrim($path, '/'));

            // ensure file exists
            if (! file_exists($storagePath)) {
                continue;
            }

            // ensure minimum width to avoid low-quality/upsized banners
            try {
                [$width, $height] = getimagesize($storagePath);
            } catch (\Throwable $e) {
                logger()->warning('SliderObserver: unable to read image size', ['path' => $storagePath, 'error' => $e->getMessage()]);

                continue;
            }

            if (empty($width) || $width < 1600) {
                logger()->warning('SliderObserver: skipping image below minimum width', ['path' => $storagePath, 'width' => $width]);

                continue;
            }

            // check if this file already exists in media collection by file name
            $fileName = Str::afterLast($path, '/');
            $exists = $slider->media()->where('file_name', $fileName)->where('collection_name', 'sliders')->exists();

            if ($exists) {
                continue;
            }

            // add to medialibrary; conversions defined on model will run
            try {
                $slider->addMedia($storagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('sliders');
            } catch (\Exception $e) {
                // don't interrupt saving process; just continue
                logger()->error('SliderObserver: failed to add media', ['path' => $storagePath, 'error' => $e->getMessage()]);
            }
        }
    }
}
