<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Page extends Model implements HasMedia
{
    use HasFactory, HasTranslations, HasUlids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'seo_description',
        'description',
        'map_embed_url',
    ];

    /**
     * Exact hosts a map iframe may point to. Must stay in sync with the
     * frontend project's `frame-src` CSP directive and its copy of this list.
     */
    public const ALLOWED_MAP_EMBED_HOSTS = [
        'www.google.com',
        'maps.google.com',
    ];

    /**
     * Hosts that are rewritten to an allowed host before validation.
     */
    private const MAP_EMBED_HOST_ALIASES = [
        'google.com' => 'www.google.com',
    ];

    /**
     * Important: Title and SEO description are stored as JSON.
     */
    protected $casts = [
        'title' => 'array',
        'seo_description' => 'array',
        'description' => 'array',
    ];

    public static function normalizeMapEmbedUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $value, $matches)) {
            $value = $matches[1];
        }

        $value = html_entity_decode(trim($value), ENT_QUOTES, 'UTF-8');

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        if (! in_array(Str::lower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        // Embedded credentials or a non-standard port would make the iframe
        // target differ from what the frontend CSP frame-src allowlist expects.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = Str::lower($parts['host']);
        $host = self::MAP_EMBED_HOST_ALIASES[$host] ?? $host;
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        $isGoogleHost = in_array($host, self::ALLOWED_MAP_EMBED_HOSTS, true);
        $isEmbedPath = Str::startsWith($path, '/maps/embed') || Str::contains($query, 'output=embed');

        if (! $isGoogleHost || ! $isEmbedPath) {
            return null;
        }

        $normalizedUrl = 'https://'.$host.$path;

        if ($query !== '') {
            $normalizedUrl .= '?'.$query;
        }

        return $normalizedUrl;
    }

    /**
     * Defining the small preview (thumb) optimization
     * used in the table list here.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 100, 100)
            ->nonQueued();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('page_images')
            ->useDisk('public'); // Ensures files always go to the 'public' disk (storage/app/public).
    }

    public function getDisplayTitleAttribute()
    {
        return $this->getTranslated('title', 'No Title');
    }

    public function getMapEmbedUrlAttribute($value): ?string
    {
        return self::normalizeMapEmbedUrl($value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
