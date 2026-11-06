<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeCreate(): void
    {
        $roles = data_get($this->form->getRawState(), 'roles', []);

        if (filled($roles) && ! UserResource::canAssignRoles()) {
            throw new AuthorizationException;
        }
    }
}
