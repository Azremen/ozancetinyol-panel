<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Url extends Model
{
    use HasUlids, LogsActivity, SoftDeletes;

    // Main solution to error: Adding these two columns to the list
    protected $fillable = [
        'slug',
        'page_id',
    ];

    /**
     * The page this URL belongs to.
     */
    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }
}
