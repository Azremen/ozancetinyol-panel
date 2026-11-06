<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // PREVIEW IMAGE
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('panel.pages.table.columns.image'))
                    ->collection('page_images') // Must match the name given in the form
                    ->circular() // Makes it appear circular
                    ->stacked() // Stacks multiple images if present
                    ->conversion('thumb'), // 'cropped' version

                TextColumn::make('display_title')
                    ->label(__('panel.pages.form.fields.title'))
                    ->searchable(query: fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('panel.pages.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
