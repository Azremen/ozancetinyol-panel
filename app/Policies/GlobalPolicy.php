<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GlobalPolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole(['Admin', 'admin', 'Developer', 'developer']);
    }

    protected function resolveBaseName($model = null): ?string
    {
        if (is_string($model) && class_exists($model)) {
            return Str::snake(class_basename($model));
        }

        if (is_object($model)) {
            return Str::snake(class_basename($model));
        }

        // Try to infer from current Filament route name: filament.resources.{resource}.*
        try {
            $route = request()->route();
            if ($route && $name = $route->getName()) {
                // Filament v5 route names include panel ID: filament.{panel}.resources.{resource}.*
                // Also support legacy format without panel ID: filament.resources.{resource}.*
                if (preg_match('/filament\.\w+\.resources\.([^.]+)\.|filament\.resources\.([^.]+)\./', $name, $m)) {
                    $resource = $m[1] !== '' ? $m[1] : $m[2];

                    // Resource names are usually plural, so convert to singular.
                    return Str::snake(Str::singular($resource));
                }
            }
        } catch (\Exception $e) {
            // ignore when request not available
        }

        return null;
    }

    protected function hasPermissionFor(User $user, string $ability, $model = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        $base = $this->resolveBaseName($model);
        if (empty($base)) {
            // Unknown context: deny by default (do not allow access_panel to grant everything).
            return false;
        }

        // Permission mapping:
        //   viewAny/view → list_* / read_*
        //   create       → create_* only  (write_* does NOT grant create)
        //   update       → write_* OR update_*
        //   delete/deleteAny → delete_* only  (write_* does NOT grant delete)
        //   forceDelete/restore → admin only (handled above, never reaches here)
        $checks = [];
        switch ($ability) {
            case 'viewAny':
                $checks = ["list_{$base}", "view_any_{$base}"];
                break;
            case 'view':
                $checks = ["read_{$base}", "view_{$base}"];
                break;
            case 'create':
                $checks = ["create_{$base}"];
                break;
            case 'update':
                $checks = ["write_{$base}", "update_{$base}"];
                break;
            case 'delete':
            case 'deleteAny':
                $checks = ["delete_{$base}"];
                break;
            default:
                return false;
        }

        foreach ($checks as $perm) {
            try {
                if ($user->hasPermissionTo($perm)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return false;
    }

    public function viewAny(User $user, ?string $model = null)
    {
        return $this->hasPermissionFor($user, 'viewAny', $model);
    }

    public function view(User $user, $model = null)
    {
        return $this->hasPermissionFor($user, 'view', $model);
    }

    public function create(User $user, ?string $model = null)
    {
        return $this->hasPermissionFor($user, 'create', $model);
    }

    public function update(User $user, $model = null)
    {
        return $this->hasPermissionFor($user, 'update', $model);
    }

    public function delete(User $user, $model = null)
    {
        return $this->hasPermissionFor($user, 'delete', $model);
    }

    public function deleteAny(User $user, ?string $model = null)
    {
        return $this->hasPermissionFor($user, 'deleteAny', $model);
    }

    public function restore(User $user, $model = null)
    {
        return $this->isAdmin($user);
    }

    public function restoreAny(User $user)
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, $model = null)
    {
        return $this->isAdmin($user);
    }

    public function forceDeleteAny(User $user)
    {
        return $this->isAdmin($user);
    }
}
