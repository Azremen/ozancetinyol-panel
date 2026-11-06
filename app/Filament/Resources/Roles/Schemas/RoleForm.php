<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $components = [
            TextInput::make('name')
                ->required(),

            TextInput::make('guard_name')
                ->default('web')
                ->required(),
        ];

        // Build grouped checkbox lists (one CheckboxList per permission group).
        $groups = static::permissionGroups();

        foreach ($groups as $slug => $group) {
            $components[] = Section::make($group['label'])->schema([
                CheckboxList::make("permissions_groups.$slug")
                    ->label($group['label'])
                    ->options($group['options'])
                    ->columns(2)
                    ->dehydrated(true),
            ]);
        }

        return $schema->components($components);
    }

    /**
     * Returns permission groups used by the form.
     *
     * @return array<string, array{label:string,options:array<int,string>}>
     */
    public static function permissionGroups(): array
    {
        $perms = Permission::where('guard_name', 'web')->get();

        $groups = [];

        foreach ($perms as $p) {
            $name = $p->name;

            $action = null;
            $resource = null;

            if (preg_match('/^(list|read|write)_(.+)$/', $name, $m)) {
                $action = $m[1];
                $resource = $m[2];
            } elseif (preg_match('/^view_any_(.+)$/', $name, $m)) {
                $action = 'list';
                $resource = $m[1];
            } elseif (preg_match('/^view_(.+)$/', $name, $m)) {
                $action = 'read';
                $resource = $m[1];
            } elseif (preg_match('/^(create|update|delete)_(.+)$/', $name, $m)) {
                $action = $m[1];
                $resource = $m[2];
            }

            $actionLabels = [
                'list' => __('panel.permissions.actions.list') ?: 'List',
                'read' => __('panel.permissions.actions.read') ?: 'Read',
                'create' => __('panel.permissions.actions.create') ?: 'Create',
                'update' => __('panel.permissions.actions.update') ?: 'Update',
                'delete' => __('panel.permissions.actions.delete') ?: 'Delete',
                'write' => __('panel.permissions.actions.write') ?: 'Write',
            ];

            // Compute a human-friendly resource label (localized when available).
            if ($resource) {
                $resourceLabel = __('panel.common.'.$resource) !== 'panel.common.'.$resource
                    ? __('panel.common.'.$resource)
                    : ucwords(str_replace(['_', '.', '-'], ' ', $resource));
            }

            // Try explicit permission translations and common labels first, else build a readable label.
            if (__('panel.permissions.'.$name) !== 'panel.permissions.'.$name) {
                $permLabel = __('panel.permissions.'.$name);
            } elseif (__('permissions.'.$name) !== 'permissions.'.$name) {
                $permLabel = __('permissions.'.$name);
            } elseif ($resource && $action) {
                $permLabel = ($actionLabels[$action] ?? ucwords($action)).' '.$resourceLabel;
            } elseif ($action) {
                $permLabel = $actionLabels[$action] ?? ucwords($action);
            } elseif ($resource) {
                // Standalone resource-level permission.
                $permLabel = $resourceLabel;
            } elseif (__('panel.common.'.$name) !== 'panel.common.'.$name) {
                $permLabel = __('panel.common.'.$name);
            } else {
                $permLabel = ucwords(str_replace(['_', '.', '-'], ' ', $name));
            }

            if (in_array($name, ['access_panel'])) {
                $slug = 'system';
                $groups[$slug]['label'] = __('panel.common.system_permissions') !== 'panel.common.system_permissions'
                    ? __('panel.common.system_permissions')
                    : 'Sistem Yetkileri';
                $groups[$slug]['options'][$p->id] = $permLabel;
            } elseif ($resource) {
                $slug = str_replace('.', '_', $resource);

                $groups[$slug]['label'] = $resourceLabel;
                $groups[$slug]['options'][$p->id] = $permLabel;
            } else {
                $groups['other']['label'] = __('panel.common.other') !== 'panel.common.other' ? __('panel.common.other') : 'Other';
                $groups['other']['options'][$p->id] = $permLabel;
            }
        }

        return $groups;
    }
}
