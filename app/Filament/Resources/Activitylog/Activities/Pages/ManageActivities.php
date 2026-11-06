<?php

namespace App\Filament\Resources\Activitylog\Activities\Pages;

use App\Filament\Resources\Activitylog\Activities\ActivityResource;
use Filament\Resources\Pages\ManageRecords;

class ManageActivities extends ManageRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
