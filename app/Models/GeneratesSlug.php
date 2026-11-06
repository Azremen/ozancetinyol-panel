<?php

namespace App\Models;

use Illuminate\Support\Str;

trait GeneratesSlug
{
    /**
     * The site's configured default language, when it is a supported locale.
     */
    protected function defaultSlugLocale(): ?string
    {
        try {
            $setting = Setting::where('key', 'default_language')->first();
            $defaultLanguage = $setting ? $setting->value : null;

            if (is_array($defaultLanguage)) {
                $defaultLanguage = $defaultLanguage[app()->getLocale()] ?? current($defaultLanguage);
            }

            if ($defaultLanguage && in_array($defaultLanguage, ['tr', 'en'], true)) {
                return $defaultLanguage;
            }
        } catch (\Throwable $exception) {
            // settings table may not exist yet during migrations
            if (! app()->runningUnitTests()) {
                report($exception);
            }
        }

        return null;
    }

    /**
     * Slugify the source and guarantee uniqueness, excluding the current record
     * only when it already has an id.
     */
    protected function uniqueSlug(string $baseSource): string
    {
        $base = Str::slug($baseSource);
        $slug = $base;
        $counter = 1;

        while (static::query()
            ->when($this->id !== null, fn ($query) => $query->where('id', '<>', $this->id))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
