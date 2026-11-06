<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Facility extends Model
{
    use HasFactory, HasTranslations, HasUlids, LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];

    public function getDisplayTitleAttribute()
    {
        return $this->getTranslated('title');
    }

    public function getDisplayDescriptionAttribute()
    {
        return $this->getTranslated('description');
    }

    // Alias for consistency with other resources that use `display_subtitle`
    public function getDisplaySubtitleAttribute()
    {
        return $this->getDisplayDescriptionAttribute();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
