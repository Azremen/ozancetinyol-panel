<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Slider extends Model implements HasMedia
{
    use HasFactory, HasTranslations, HasUlids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'button_link',
        'image',
        'images',
        'order',
        'is_active',
    ];

    protected $casts = [
        'title' => 'array',
        'subtitle' => 'array',
        'button_text' => 'array',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Helper functions to get value based on active language
     */
    public function getDisplayTitleAttribute()
    {
        return $this->getTranslated('title');
    }

    public function getDisplaySubtitleAttribute()
    {
        return $this->getTranslated('subtitle');
    }

    public function getDisplayButtonTextAttribute()
    {
        return $this->getTranslated('button_text');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    /**
     * Register media conversions for slider images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('hero_1920x700')
            ->fit(Fit::Contain, 1920, 700)
            ->performOnCollections('sliders')
            ->nonQueued();

        $this->addMediaConversion('hero_1600x600')
            ->fit(Fit::Contain, 1600, 600)
            ->performOnCollections('sliders')
            ->nonQueued();

        // optional webp conversion for smaller payloads
        $this->addMediaConversion('hero_1920x700_webp')
            ->fit(Fit::Contain, 1920, 700)
            ->format('webp')
            ->performOnCollections('sliders')
            ->nonQueued();
    }

    public function getImageAttribute($value)
    {
        // Keep backward compatibility: return single `image` if set,
        // otherwise return first entry from `images` array when available.
        if (! empty($value)) {
            return $value;
        }

        $imgs = $this->images;
        if (is_array($imgs) && count($imgs) > 0) {
            return $imgs[0];
        }

        return $value;
    }
}
