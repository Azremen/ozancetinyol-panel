<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use HasFactory, HasTranslations, HasUlids, LogsActivity;

    protected $fillable = ['key', 'value', 'type'];

    public const MASKED_VALUE = '********';

    /**
     * The Value column is cast to JSON to support multiple languages.
     */
    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Helper function to get the value based on active language.
     */
    public function getDisplayValueAttribute(): string
    {
        return $this->getTranslated('value');
    }

    /**
     * Static helper method to easily access settings from anywhere.
     * Results are cached for 6 hours to avoid repeated DB hits.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember('setting.'.$key, now()->addHours(6), function () use ($key) {
            return static::where('key', $key)->first();
        });

        return $setting ? $setting->display_value : $default;
    }

    /**
     * Clear the cached value for a given key.
     */
    public static function forgetCache(string $key): void
    {
        Cache::forget('setting.'.$key);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    /**
     * Settings whose values are credentials. Their values are masked before the
     * activity record is persisted so they never appear in the activity log.
     */
    public static function isSecretKey(?string $key): bool
    {
        return is_string($key) && preg_match('/secret|password|token|api[_-]?key/i', $key) === 1;
    }

    public function tapActivity(Activity&Model $activity, string $eventName): void
    {
        if (! self::isSecretKey($this->key)) {
            return;
        }

        $properties = $activity->getAttribute('properties');

        foreach (['attributes', 'old'] as $bucket) {
            if (isset($properties[$bucket]['value'])) {
                $bucketData = $properties[$bucket];
                $bucketData['value'] = self::MASKED_VALUE;
                $properties[$bucket] = $bucketData;
            }
        }

        $activity->setAttribute('properties', $properties);
    }

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            $key = $setting->key;
            DB::afterCommit(fn () => self::forgetCache($key));
        });

        static::deleted(function (self $setting): void {
            $key = $setting->key;
            DB::afterCommit(fn () => self::forgetCache($key));
        });
    }
}
