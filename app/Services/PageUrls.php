<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Url;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageUrls
{
    public static function canonical(Page $page): ?Url
    {
        return Url::where('page_id', $page->id)->orderBy('id')->first();
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '//') || preg_match('/[\\\\?#:%\\x00-\\x1f]/', $value)) {
            return null;
        }
        $segments = explode('/', trim($value, '/'));
        foreach ($segments as &$segment) {
            if ($segment === '' || in_array($segment, ['.', '..'], true)) {
                return null;
            }
            $segment = Str::slug($segment);
            if ($segment === '') {
                return null;
            }
        }

        return implode('/', $segments);
    }

    public static function error(?string $value, ?Url $current = null): ?string
    {
        $slug = self::normalize($value);
        if (! $slug || strlen($slug) > 255) {
            return __('cms.slug_invalid');
        }
        if ($current && self::normalize($current->slug) === $slug) {
            return null;
        }
        $first = explode('/', $slug)[0];
        if (in_array($first, ['tr', 'en', 'cerez-politikasi', 'kvkk-ve-gizlilik', 'appointment-request',
            'lang', 'sitemap-xml', 'sitemapxml', 'sitemap.xml', 'login', 'logout', 'admin', 'panel', 'livewire',
            'storage', 'build', 'img', 'up', 'api', 'home'], true)) {
            return __('cms.slug_reserved');
        }
        foreach (Url::withTrashed()->where('id', '!=', $current?->id ?? '')->get(['id', 'slug']) as $url) {
            if (self::normalize($url->slug) === $slug) {
                return __('cms.slug_unique');
            }
        }

        return null;
    }

    public static function save(Page $page, string $value): void
    {
        $current = self::canonical($page);
        if ($error = self::error($value, $current)) {
            throw ValidationException::withMessages(['data.slug' => $error]);
        }
        $slug = self::normalize($value);
        if ($current && self::normalize($current->slug) === $slug) {
            return;
        }
        if ($current) {
            $oldSlug = $current->slug;
            $current->update(['slug' => $slug]);
            Url::create(['page_id' => $page->id, 'slug' => $oldSlug]);
        } else {
            Url::create(['page_id' => $page->id, 'slug' => $slug]);
        }
    }
}
