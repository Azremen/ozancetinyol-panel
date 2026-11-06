<?php

namespace App\Filament\Resources\AppointmentRequests\Pages;

use App\Filament\Resources\AppointmentRequests\AppointmentRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointmentRequest extends CreateRecord
{
    protected static string $resource = AppointmentRequestResource::class;
}
