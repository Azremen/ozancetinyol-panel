<?php

namespace App\Filament\Resources\AppointmentRequests\Pages;

use App\Filament\Pages\AppointmentSettings;
use App\Filament\Resources\AppointmentRequests\AppointmentRequestResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointmentRequests extends ListRecords
{
    protected static string $resource = AppointmentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')->label(__('appointments.calendar'))
                ->url(AppointmentRequestResource::getUrl('calendar')),
            Action::make('schedule')->label(__('appointments.settings'))
                ->url(fn () => AppointmentSettings::getUrl())->visible(fn () => AppointmentSettings::canAccess()),
            CreateAction::make(),
        ];
    }
}
