<?php

namespace App\Filament\Resources\AppointmentRequests\Tables;

use App\Filament\Resources\AppointmentRequests\AppointmentRequestResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AppointmentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('physio.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('physio.phone'))
                    ->searchable(),
                TextColumn::make('email')->label(__('appointments.email'))->searchable(),
                TextColumn::make('preferred_date')
                    ->label(__('physio.preferred_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('preferred_time')->label(__('appointments.time'))->sortable(),
                TextColumn::make('status')
                    ->label(__('physio.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("physio.status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'contacted' => 'info',
                        'confirmed' => 'success',
                        'cancelled' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label(__('physio.received_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('physio.status'))
                    ->options([
                        'pending' => __('physio.status_pending'),
                        'contacted' => __('physio.status_contacted'),
                        'confirmed' => __('physio.status_confirmed'),
                        'cancelled' => __('physio.status_cancelled'),
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn ($record): bool => AppointmentRequestResource::canDelete($record)),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
