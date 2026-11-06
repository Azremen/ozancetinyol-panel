<?php

namespace App\Filament\Resources\Urls;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Urls\Pages\CreateUrl;
use App\Filament\Resources\Urls\Pages\EditUrl;
use App\Filament\Resources\Urls\Pages\ListUrls;
use App\Filament\Resources\Urls\Schemas\UrlForm;
use App\Models\Url;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
/**
 * CRITICAL IMPORTS: This block solves all "Unknown class" errors.
 */
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UrlResource extends BaseResource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Url::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'slug';

    // --- Translation Methods ---
    public static function getNavigationLabel(): string
    {
        return __('panel.urls.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('panel.urls.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.urls.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('panel.common.sistem_y_netimi');
    }

    public static function form(Schema $schema): Schema
    {
        return UrlForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['page']))
            ->columns([
                TextColumn::make('slug')
                    ->label(__('panel.urls.table.columns.slug'))
                    ->prefix('/')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('page.display_title')
                    ->label(__('panel.urls.form.fields.target_model.types.page'))
                    ->placeholder(__('panel.common.no_page_selected'))
                    ->searchable(query: fn ($query, $search) => $query->whereHas('page', function ($q) use ($search) {
                        return $q->whereRaw('LOWER(title) LIKE ?', ['%'.strtolower($search).'%']);
                    })),

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUrls::route('/'),
            'create' => CreateUrl::route('/create'),
            'edit' => EditUrl::route('/{record}/edit'),
        ];
    }
}
