<?php

namespace App\Filament\Resources\Facilities\Tables;

use App\Filament\Resources\Facilities\FacilityResource;
use App\Helpers\IconHelper;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class FacilitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label(__('panel.common.icon_class'))
                    ->formatStateUsing(function ($state) {
                        $iconClass = IconHelper::ICON_CLASSES[$state] ?? ($state === 'flaticon-parking' ? 'flaticon-car' : $state);

                        return new HtmlString("<i class='".e($iconClass)."' aria-hidden='true' style='font-size: 1.5rem;'></i>");
                    })
                    ->html(),
                TextColumn::make('display_title')
                    ->label(__('panel.common.title'))
                    ->formatStateUsing(function ($state, $record) {
                        if (! empty($state)) {
                            return $state;
                        }

                        // Try model accessor
                        if (is_object($record) && isset($record->display_title) && ! empty($record->display_title)) {
                            return $record->display_title;
                        }

                        // Get raw title value whether record is array or object
                        $raw = is_array($record) ? ($record['title'] ?? null) : ($record->title ?? null);
                        if (is_array($raw)) {
                            return $raw[app()->getLocale()] ?? $raw['tr'] ?? $raw['en'] ?? '';
                        }
                        if (is_string($raw) && $raw !== '') {
                            $decoded = json_decode($raw, true);
                            if (is_array($decoded)) {
                                return $decoded[app()->getLocale()] ?? $decoded['tr'] ?? $decoded['en'] ?? '';
                            }

                            return $raw;
                        }

                        return '';
                    }),
                TextColumn::make('display_subtitle')
                    ->label(__('panel.common.subtitle'))
                    ->formatStateUsing(function ($state, $record) {
                        if (! empty($state)) {
                            return $state;
                        }

                        if (is_object($record) && isset($record->display_subtitle) && ! empty($record->display_subtitle)) {
                            return $record->display_subtitle;
                        }

                        $raw = is_array($record) ? ($record['description'] ?? null) : ($record->description ?? null);
                        if (is_array($raw)) {
                            return $raw[app()->getLocale()] ?? $raw['tr'] ?? $raw['en'] ?? '';
                        }
                        if (is_string($raw) && $raw !== '') {
                            $decoded = json_decode($raw, true);
                            if (is_array($decoded)) {
                                return $decoded[app()->getLocale()] ?? $decoded['tr'] ?? $decoded['en'] ?? '';
                            }

                            return $raw;
                        }

                        return '';
                    }),
                TextColumn::make('order')
                    ->label(__('panel.common.order'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('panel.common.active'))
                    ->disabled(fn ($record): bool => ! FacilityResource::canEdit($record))
                    ->beforeStateUpdated(fn ($record) => abort_unless(FacilityResource::canEdit($record), 403)),
            ])
            ->defaultSort('order', 'asc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn ($record) => (function ($r) {
                        $appUrl = rtrim(config('app.url'), '/');
                        $path = parse_url($appUrl, PHP_URL_PATH) ?? '';
                        if (str_ends_with($path, '/admin')) {
                            $appUrl = substr($appUrl, 0, -strlen('/admin'));
                        }

                        return rtrim($appUrl, '/').'/facilities/'.($r->id ?? $r['id'] ?? '').'/edit';
                    })($record)),
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
