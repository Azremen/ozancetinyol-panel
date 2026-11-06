<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $groups = RoleForm::permissionGroups();

        $permissionIds = $this->getRecord()->permissions()->pluck('id')->toArray();

        foreach ($groups as $slug => $group) {
            $ids = array_values(array_intersect($permissionIds, array_keys($group['options'])));
            $data['permissions_groups'][$slug] = $ids;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $permissions = $data['permissions'] ?? null;

        // Remove temporary grouped states before updating attributes
        unset($data['permissions_groups'], $data['permissions']);

        $record = parent::handleRecordUpdate($record, $data);

        if (! is_null($permissions)) {
            $record->permissions()->sync($permissions);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
