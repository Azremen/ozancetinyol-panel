<?php

namespace App\Observers;

use Spatie\Permission\Models\Role;

class RoleObserver
{
    public function created(Role $role): void
    {
        activity('role')->performedOn($role)->log('created');
    }

    public function updated(Role $role): void
    {
        activity('role')->performedOn($role)->log('updated');
    }

    public function deleted(Role $role): void
    {
        activity('role')->performedOn($role)->log('deleted');
    }
}
