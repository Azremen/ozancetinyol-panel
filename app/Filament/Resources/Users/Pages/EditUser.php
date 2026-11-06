<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeSave(): void
    {
        $roles = data_get($this->form->getRawState(), 'roles');

        if ($roles === null || UserResource::canAssignRoles()) {
            return;
        }

        $submittedRoleIds = collect($roles)->map(static fn ($role): string => (string) $role)->sort()->values();
        $currentRoleIds = $this->getRecord()->roles()->pluck('roles.id')->map(static fn ($role): string => (string) $role)->sort()->values();

        if ($submittedRoleIds->all() !== $currentRoleIds->all()) {
            throw new AuthorizationException;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
