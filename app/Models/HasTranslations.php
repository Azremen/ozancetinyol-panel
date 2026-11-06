<?php

namespace App\Models;

trait HasTranslations
{
    /**
     * Resolve a translatable (JSON) attribute for the active locale.
     *
     * Fallback order: active locale -> Turkish -> English -> first available
     * value. Non-array values are returned as-is.
     */
    protected function getTranslated(string $attribute, string $default = ''): string
    {
        $translations = $this->{$attribute};

        if (! is_array($translations)) {
            return (string) ($translations ?? $default);
        }

        $locale = app()->getLocale();

        return (string) (
            $translations[$locale]
            ?? $translations['tr']
            ?? $translations['en']
            ?? (current($translations) ?: $default)
        );
    }
}
