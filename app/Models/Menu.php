<?php

namespace App\Models;

use App\Services\MenuStructure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Menu extends Model
{
    use HasTranslations, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = ['parent_id', 'type', 'title', 'custom_url', 'page_id', 'order', 'meta_title', 'meta_description'];

    protected $casts = ['title' => 'array'];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * The menu's URL connection. (One-to-One) - OLD Architecture
     */
    public function url()
    {
        return $this->hasOne(Url::class);
    }

    /**
     * Helper attribute (Accessor) to easily fetch the link.
     */
    public function getLinkAttribute()
    {
        // 1. Use custom URL if exists
        if (! empty($this->custom_url)) {
            $custom = trim($this->custom_url);

            // Full external URLs -> return as-is
            if (preg_match('/^https?:\/\//', $custom) || str_starts_with($custom, '//')) {
                return $custom;
            }

            // Anchors like "#about" or "/#about" -> resolve to homepage + fragment
            if (str_starts_with($custom, '#') || str_starts_with($custom, '/#')) {
                $fragment = '#'.ltrim($custom, '#/');

                return rtrim(url('/'), '/').$fragment;
            }

            // Relative or other custom paths -> return as-is
            return $custom;
        }

        // 2. If a page is selected, find its URL
        if ($this->page_id) {
            $pageUrl = Url::where('page_id', $this->page_id)->orderBy('id')->first();
            if ($pageUrl) {
                return url($pageUrl->slug);
            }
        }

        // 3. Old method (via menu_id from URL table)
        return $this->url ? url($this->url->slug) : '#';
    }

    public function getDisplayTitleAttribute()
    {
        return $this->getTranslated('title', 'No Title');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::saving(function (Menu $menu): void {
            if ($error = MenuStructure::error($menu->exists ? $menu : null, $menu->type, $menu->parent_id)) {
                throw ValidationException::withMessages(['parent_id' => $error]);
            }
        });

        static::creating(function (Menu $menu) {
            // If order is not provided, place the menu at the end within the same
            // `type` and `parent_id` scope so header/footer/sidebar groups keep
            // independent ordering.
            if (is_null($menu->order)) {
                $max = self::where('type', $menu->type)
                    ->where('parent_id', $menu->parent_id)
                    ->max('order');

                $menu->order = is_null($max) ? 0 : ($max + 1);
            }
        });
    }

    public function moveUp(): void
    {
        $neighbor = self::where('type', $this->type)
            ->where('parent_id', $this->parent_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();

        if (! $neighbor) {
            return;
        }

        $temp = $this->order;
        $this->order = $neighbor->order;
        $neighbor->order = $temp;

        $this->save();
        $neighbor->save();
    }

    public function moveDown(): void
    {
        $neighbor = self::where('type', $this->type)
            ->where('parent_id', $this->parent_id)
            ->where('order', '>', $this->order)
            ->orderBy('order', 'asc')
            ->first();

        if (! $neighbor) {
            return;
        }

        $temp = $this->order;
        $this->order = $neighbor->order;
        $neighbor->order = $temp;

        $this->save();
        $neighbor->save();
    }
}
