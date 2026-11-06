<?php

namespace App\Filament\Resources\AppointmentRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class AppointmentRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('physio.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('physio.phone'))
                    ->tel()
                    ->required()
                    ->maxLength(30),
                TextInput::make('email')->label(__('appointments.email'))->email()->required()->maxLength(255),
                DatePicker::make('preferred_date')
                    ->label(__('physio.preferred_date'))
                    ->native(false),
                TimePicker::make('preferred_time')->label(__('appointments.time'))->seconds(false)->format('H:i'),
                Select::make('status')
                    ->label(__('physio.status'))
                    ->options([
                        'pending' => __('physio.status_pending'),
                        'contacted' => __('physio.status_contacted'),
                        'confirmed' => __('physio.status_confirmed'),
                        'cancelled' => __('physio.status_cancelled'),
                    ])
                    ->required(),
                Textarea::make('note')
                    ->label(__('physio.note'))
                    ->rows(5)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
