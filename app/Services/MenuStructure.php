<?php

namespace App\Services;

use App\Models\Menu;

class MenuStructure
{
    public static function error(?Menu $record, ?string $type, ?string $parentId): ?string
    {
        if ($type === 'sidebar' && $record?->getOriginal('type') === 'sidebar') {
            if ($parentId && $parentId !== $record->getOriginal('parent_id')) {
                return __('cms.sidebar_parent_error');
            }

            return null;
        }

        if (! in_array($type, ['header', 'footer'], true)) {
            return __('cms.menu_type_error');
        }

        $children = $record?->children()->get() ?? collect();
        if ($type === 'footer') {
            return $parentId || $children->isNotEmpty() ? __('cms.footer_parent_error') : null;
        }

        if ($parentId) {
            $parent = Menu::find($parentId);
            if (! $parent || $parent->id === $record?->id || $parent->type !== 'header'
                || $parent->parent_id || $children->isNotEmpty()) {
                return __('cms.header_parent_error');
            }
        }

        foreach ($children as $child) {
            if ($child->type !== 'header' || $child->children()->exists()) {
                return __('cms.header_parent_error');
            }
        }

        return null;
    }
}
