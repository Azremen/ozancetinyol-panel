<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use App\Services\PageUrls;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')->label(__('cms.slug'))->prefix('/')
                    ->helperText(__('cms.slug_help'))->required()->maxLength(255)
                    ->rule(fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record): void {
                        if ($error = PageUrls::error($value, $record ? PageUrls::canonical($record) : null)) {
                            $fail($error);
                        }
                    }),
                Tabs::make('Languages')
                    ->tabs([
                        // TURKISH TAB
                        Tabs\Tab::make('tr') // Set tab name to 'tr', provide label via label()
                            ->label(__('panel.common.turkish'))
                            ->icon('heroicon-m-language')
                            ->schema([
                                TextInput::make('title.tr')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get, $record): void {
                                        if (! $record && blank($get('slug'))) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    })
                                    ->label(__('panel.pages.form.fields.title').' (TR)')
                                    ->required()
                                    ->extraInputAttributes(['aria-label' => __('panel.pages.form.fields.title').' (TR)']),
                                TextInput::make('seo_description.tr')
                                    ->label(__('panel.pages.form.fields.seo_description').' (TR)')
                                    ->extraInputAttributes(['aria-label' => __('panel.pages.form.fields.seo_description').' (TR)']),
                                RichEditor::make('description.tr')
                                    ->label(__('panel.pages.form.fields.description').' (TR)')
                                    ->extraAttributes(['style' => 'min-height: 320px;'])
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link', 'textColor'],
                                        ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                        ['table', 'attachFiles'],
                                        ['undo', 'redo'],
                                    ])
                                    ->textColors([
                                        ...TextColor::getDefaults(),
                                    ])
                                    ->customTextColors(),
                            ]),

                        // ENGLISH TAB
                        Tabs\Tab::make('en')
                            ->label(__('panel.common.english'))
                            ->icon('heroicon-m-language')
                            ->schema([
                                TextInput::make('title.en')
                                    ->label(__('panel.pages.form.fields.title').' (EN)')
                                    ->extraInputAttributes(['aria-label' => __('panel.pages.form.fields.title').' (EN)']),
                                TextInput::make('seo_description.en')
                                    ->label(__('panel.pages.form.fields.seo_description').' (EN)')
                                    ->extraInputAttributes(['aria-label' => __('panel.pages.form.fields.seo_description').' (EN)']),
                                RichEditor::make('description.en')
                                    ->label(__('panel.pages.form.fields.description').' (EN)')
                                    ->extraAttributes(['style' => 'min-height: 320px;'])
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link', 'textColor'],
                                        ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                        ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                        ['table', 'attachFiles'],
                                        ['undo', 'redo'],
                                    ])
                                    ->textColors([
                                        ...TextColor::getDefaults(),
                                    ])
                                    ->customTextColors(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('map_section')
                    ->heading(__('panel.pages.form.fields.map_embed'))
                    ->schema([
                        Textarea::make('map_embed_url')
                            ->label(__('panel.pages.form.fields.map_embed'))
                            ->helperText(__('panel.pages.form.fields.map_embed_helper'))
                            ->placeholder(__('panel.pages.form.fields.map_embed_placeholder'))
                            ->rows(4)
                            ->columnSpanFull()
                            ->dehydrateStateUsing(fn (?string $state): ?string => Page::normalizeMapEmbedUrl($state))
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    if (! Page::normalizeMapEmbedUrl((string) $value)) {
                                        $fail(__('panel.pages.form.fields.map_embed_validation'));
                                    }
                                };
                            }),
                    ]),

                Section::make('media_section')
                    ->heading(__('panel.pages.form.fields.image'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('image')
                            ->collection('page_images')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
