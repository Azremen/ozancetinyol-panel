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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use LogicException;

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
                            $url = self::resolvePreviewUrl($img);

                            if ($url === null) {
                                continue;
                            }

                            $html .= '<img src="'.e($url).'" alt="" style="height:40px;object-fit:cover;margin-right:6px;border-radius:4px" />';
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

    /**
     * Stored slider paths come from FileUpload state, which a client can tamper
     * with. Only relative paths on the public disk (or absolute HTTP(S) URLs) are
     * rendered, and the value is always HTML-escaped by the caller.
     */
    public static function resolvePreviewUrl(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('~^https?://~i', $path) === 1) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false ? $path : null;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
            return null;
        }

        if (preg_match('~^[A-Za-z0-9/_.\-]+$~', $relative) !== 1) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk instanceof FilesystemAdapter) {
            throw new LogicException('The public disk must support generating file URLs.');
        }

        return $disk->url($relative);
    }
}
