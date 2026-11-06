<?php

namespace App\Filament\Resources\Activitylog\Activities;

use App\Filament\Resources\Activitylog\Activities\Pages\ManageActivities;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends BaseResource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.common.system_logs') ?? 'System Logs';
    }

    public static function getModelLabel(): string
    {
        return __('panel.common.activity_log') ?? 'Activity Log';
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.common.activity_logs') ?? 'Activity Logs';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.common.activity_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('log_name')
                                    ->label(__('panel.common.log_name'))
                                    ->badge(),
                                TextEntry::make('event')
                                    ->label(__('panel.common.event'))
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        'created' => 'success',
                                        'updated' => 'warning',
                                        'deleted' => 'danger',
                                        default => 'primary',
                                    }),
                                TextEntry::make('description')
                                    ->label(__('panel.common.description')),
                                TextEntry::make('subject_type')
                                    ->label(__('panel.common.subject')),
                                TextEntry::make('causer.name')
                                    ->label(__('panel.common.causer')),
                                TextEntry::make('created_at')
                                    ->label(__('panel.common.created_at'))
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make(__('panel.common.properties'))
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                KeyValueEntry::make('properties.attributes')
                                    ->label(__('panel.common.new_values')),
                                KeyValueEntry::make('properties.old')
                                    ->label(__('panel.common.old_values')),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->properties?->has('attributes') || $record->properties?->has('old')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('log_name')
                    ->label(__('panel.common.log_name') ?? 'Log Name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('panel.common.description') ?? 'Description')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label(__('panel.common.subject') ?? 'Subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label(__('panel.common.causer') ?? 'User')
                    ->searchable(condition: true, query: function (Builder $query, string $search): Builder {
                        return $query->whereHasMorph(
                            'causer',
                            [User::class],
                            function (Builder $query) use ($search) {
                                $query->where('name', 'like', "%{$search}%");
                            }
                        );
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            User::select('name')->whereColumn('activity_log.causer_id', 'users.id'),
                            $direction
                        );
                    }),
                TextColumn::make('created_at')
                    ->label(__('panel.common.created_at') ?? 'Tarih')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label(__('panel.common.log_name'))
                    ->options(fn () => Activity::select('log_name')->whereNotNull('log_name')->distinct()->pluck('log_name', 'log_name')->toArray())
                    ->multiple(),
                SelectFilter::make('event')
                    ->label(__('panel.common.event'))
                    ->options(fn () => Activity::select('event')->whereNotNull('event')->distinct()->pluck('event', 'event')->toArray())
                    ->multiple(),
                SelectFilter::make('causer_id')
                    ->label(__('panel.common.causer'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['values'])) {
                            return $query->whereIn('causer_id', $data['values'])->where('causer_type', User::class);
                        }

                        return $query;
                    }),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from')->label(__('panel.common.from_date')),
                        DatePicker::make('created_until')->label(__('panel.common.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }
}
