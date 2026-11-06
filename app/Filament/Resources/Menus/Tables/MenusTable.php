<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Models\Menu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->columns([
                TextColumn::make('display_title')
                    ->label(__('panel.menus.form.fields.title'))
                    ->searchable(query: fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('order')
                    ->label(__('panel.menus.form.fields.order'))
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('panel.menus.form.fields.type'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('structure_status')
                    ->label(__('cms.menu_status'))
                    ->state(function (Menu $record): string {
                        $displayed = ($record->type === 'footer' && ! $record->parent_id)
                            || ($record->type === 'header' && (! $record->parent_id
                                || ($record->parent?->type === 'header' && ! $record->parent->parent_id)));

                        return __($displayed ? 'cms.menu_displayed' : 'cms.menu_not_displayed');
                    })
                    ->badge(),

                // Parent Menu Info (Optional, to see hierarchy)
                TextColumn::make('parent.title')
                    ->label(__('panel.menus.form.fields.parent'))
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('panel.pages.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->icon('heroicon-m-arrow-up')
                    ->action(function (Menu $record) {
                        $record->moveUp();
                    })
                    ->tooltip(__('panel.menus.actions.move_up'))
                    ->visible(fn (Menu $record): bool => Menu::where('type', $record->type)->where('parent_id', $record->parent_id)->where('order', '<', $record->order)->exists()),

                Action::make('moveDown')
                    ->icon('heroicon-m-arrow-down')
                    ->action(function (Menu $record) {
                        $record->moveDown();
                    })
                    ->tooltip(__('panel.menus.actions.move_down'))
                    ->visible(fn (Menu $record): bool => Menu::where('type', $record->type)->where('parent_id', $record->parent_id)->where('order', '>', $record->order)->exists()),

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
            ])
            ->reorderable('order');
    }
}
