<?php

namespace App\Filament\Resources\Sliders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('images')
                    ->label(__('panel.common.image'))
                    ->html()
                    ->formatStateUsing(function ($record) {
                        $imgs = [];
                        if (! empty($record->image)) {
                            $imgs[] = $record->image;
                        }
                        if (is_array($record->images)) {
                            $imgs = array_merge($imgs, $record->images);
                        }
                        $html = '';
                        foreach (array_slice($imgs, 0, 5) as $img) {
                            $url = $img;
                            $html .= "<img src=\"{$url}\" style=\"height:40px;object-fit:cover;margin-right:6px;border-radius:4px\" />";
                        }

                        return $html;
                    }),
                TextColumn::make('title.tr')
                    ->label(__('panel.common.title_tr'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order')
                    ->label(__('panel.common.order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('panel.common.is_active'))
                    ->boolean()
                    ->sortable(),
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
            ])
            ->defaultSort('order', 'asc');
    }
}
