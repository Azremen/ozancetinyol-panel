<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('panel.common.avatar'))
                    ->circular(),
                TextColumn::make('client_name')
                    ->label(__('panel.common.client_name')),
                TextColumn::make('client_title')
                    ->label(__('panel.common.client_title')),
                TextColumn::make('rating')
                    ->label(__('panel.common.rating')),
                TextColumn::make('order')
                    ->label(__('panel.common.order'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('panel.common.active'))
                    ->disabled(fn ($record): bool => ! TestimonialResource::canEdit($record))
                    ->beforeStateUpdated(fn ($record) => abort_unless(TestimonialResource::canEdit($record), 403)),
            ])
            ->defaultSort('order', 'asc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
