<?php

namespace App\Console\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync all permissions for Filament Resources';

    public function handle()
    {
        $resources = Filament::getResources();
        $permissions = [];

        foreach ($resources as $resource) {
            $model = $resource::getModel();
            $baseName = Str::snake(class_basename($model));
            // Legacy Filament-style permissions
            $permissions[] = "view_any_{$baseName}";
            $permissions[] = "view_{$baseName}";
            $permissions[] = "create_{$baseName}";
            $permissions[] = "update_{$baseName}";
            $permissions[] = "delete_{$baseName}";

            // New three-category permissions: list/read/write
            $permissions[] = "list_{$baseName}";
            $permissions[] = "read_{$baseName}";
            $permissions[] = "write_{$baseName}";
        }

        $permissions[] = 'access_panel';

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::all());

        $this->info('Permissions synced successfully! Given to Admin role.');
    }
}
