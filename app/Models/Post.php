<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Post extends Model
{
    use HasFactory, HasTranslations, HasUlids, LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'title' => 'array',
        'category_name' => 'array',
        'publish_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function getDisplayTitleAttribute()
    {
        return $this->getTranslated('title');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
