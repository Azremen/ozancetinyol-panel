<?php

namespace App\Filament\Resources\AppointmentRequests;

use App\Filament\Resources\AppointmentRequests\Pages\AppointmentCalendar;
use App\Filament\Resources\AppointmentRequests\Pages\CreateAppointmentRequest;
use App\Filament\Resources\AppointmentRequests\Pages\EditAppointmentRequest;
use App\Filament\Resources\AppointmentRequests\Pages\ListAppointmentRequests;
use App\Filament\Resources\AppointmentRequests\Schemas\AppointmentRequestForm;
use App\Filament\Resources\AppointmentRequests\Tables\AppointmentRequestsTable;
use App\Filament\Resources\BaseResource;
use App\Models\AppointmentRequest;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AppointmentRequestResource extends BaseResource
{
    protected static ?string $model = AppointmentRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('physio.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('physio.appointment_request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('physio.appointment_requests');
    }

    public static function form(Schema $schema): Schema
    {
        return AppointmentRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointmentRequests::route('/'),
            'create' => CreateAppointmentRequest::route('/create'),
            'calendar' => AppointmentCalendar::route('/calendar'),
            'edit' => EditAppointmentRequest::route('/{record}/edit'),
        ];
    }
}
