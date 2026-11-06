<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $merged = [];

        foreach ($data['permissions_groups'] ?? [] as $groupValues) {
            if (is_array($groupValues)) {
                $merged = array_merge($merged, $groupValues);
            }
        }

        $data['permissions'] = array_values(array_unique($merged));

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $permissions = $data['permissions'] ?? null;

        // Remove temporary grouped states before creating
        unset($data['permissions_groups'], $data['permissions']);

        $record = parent::handleRecordCreation($data);

        if (! is_null($permissions)) {
            $record->permissions()->sync($permissions);
        }

        return $record;
    }
}
