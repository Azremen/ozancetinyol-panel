<?php

namespace App\Filament\Resources\Urls\Schemas;

use App\Models\Page;
use App\Services\PageUrls; // Using Select instead of MorphToSelect
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UrlForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('url_settings')
                    ->heading(__('panel.urls.form.sections.settings.heading'))
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('panel.urls.form.fields.slug.label'))
                            ->prefix('/')
                            ->required()
                            ->helperText(__('cms.alias_help'))
                            ->dehydrateStateUsing(fn ($state) => PageUrls::normalize($state))
                            ->rule(fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                                if ($error = PageUrls::error($value, $record)) {
                                    $fail($error);
                                }
                            }),

                        // PAGE SELECTION (REQUIRED)
                        Select::make('page_id')
                            ->label(__('panel.urls.form.fields.target_model.types.page'))
                            ->options(Page::all()->pluck('display_title', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(1),
            ]);
    }
}
