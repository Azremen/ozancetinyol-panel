<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Models\Menu;
use App\Models\Page;
use App\Services\MenuStructure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('menu_settings')
                ->heading(__('panel.menus.form.sections.settings.heading'))
                ->schema([
                    // TYPE SELECTION (Combobox)
                    Select::make('type')
                        ->label(__('panel.menus.form.fields.type'))
                        ->options(fn ($record) => [
                            'header' => __('panel.menus.form.types.header'),
                            'footer' => __('panel.menus.form.types.footer'),
                        ] + ($record?->type === 'sidebar' ? ['sidebar' => __('cms.sidebar_legacy')] : []))
                        ->helperText(__('cms.menu_structure_help'))
                        ->live()
                        ->rule(fn ($record, $get) => function (string $attribute, $value, \Closure $fail) use ($record, $get): void {
                            if ($error = MenuStructure::error($record, $value, $get('parent_id'))) {
                                $fail($error);
                            }
                        })
                        ->required()
                        ->searchable(),

                    // PARENT MENU SELECTION (Relationship)
                    Select::make('parent_id')
                        ->label(__('panel.menus.form.fields.parent'))
                        ->options(function ($record) {
                            $query = Menu::where('type', 'header')->whereNull('parent_id');
                            if ($record) {
                                $query->where('id', '!=', $record->id);
                            }

                            $options = $query->get()->pluck('display_title', 'id');
                            if ($record?->parent_id && ($parent = Menu::find($record->parent_id))) {
                                $options[$parent->id] = $parent->display_title;
                            }

                            return $options;
                        })
                        ->searchable()
                        ->rule(fn ($record, $get) => function (string $attribute, $value, \Closure $fail) use ($record, $get): void {
                            if ($error = MenuStructure::error($record, $get('type'), $value)) {
                                $fail($error);
                            }
                        })
                        ->placeholder(__('panel.menus.form.fields.parent_placeholder')),
                    // ORDER / SORTING
                    TextInput::make('order')
                        ->label(__('panel.menus.form.fields.order'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])->columns(2),

            Section::make('link_settings')
                ->heading(__('panel.menus.form.sections.link.heading'))
                ->schema([
                    // Linked page or homepage section (internal)
                    // This virtual select lets admins choose either an internal Page
                    // or a homepage section (anchor). When an anchor is chosen we
                    // write its fragment into `custom_url`. When a Page is chosen
                    // we set `page_id`.
                    Select::make('linked_page')
                        ->label(__('panel.menus.form.fields.page_id'))
                        ->searchable()
                        ->options(function () {
                            $pages = Page::all()->pluck('display_title', 'id')->toArray();

                            $anchors = [
                                '#about' => __('panel.menus.anchors.about'),
                                '#appointment' => __('physio.appointment_requests'),
                                '#services' => __('panel.menus.anchors.services'),
                                '#contact' => __('panel.menus.anchors.contact'),
                            ];

                            // Prefix anchor keys so they don't collide with page IDs
                            $anchorOptions = [];
                            foreach ($anchors as $fragment => $label) {
                                $anchorOptions['anchor:'.$fragment] = $label;
                            }

                            return array_merge($pages, $anchorOptions);
                        })
                        ->reactive()
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, $set) {
                            if (is_string($state) && str_starts_with($state, 'anchor:')) {
                                $fragment = substr($state, strlen('anchor:'));
                                $set('custom_url', $fragment ?: null);
                                $set('page_id', null);
                            } else {
                                $set('page_id', $state ?: null);
                                $set('custom_url', null);
                            }
                        })
                        ->afterStateHydrated(function ($state, $set, $record) {
                            // Use safe access since Filament may provide arrays/stdClass instead of model instances
                            $customUrl = data_get($record, 'custom_url');
                            $pageId = data_get($record, 'page_id');

                            if (! empty($customUrl) && str_starts_with($customUrl, '#')) {
                                $set('linked_page', 'anchor:'.$customUrl);
                            } elseif (! empty($pageId)) {
                                $set('linked_page', $pageId);
                            }
                        }),

                    Hidden::make('page_id'),

                    // 2. Custom URL (External)
                    TextInput::make('custom_url')
                        ->label(__('panel.menus.form.fields.custom_url'))
                        ->placeholder('https://google.com')
                        ->prefixIcon('heroicon-m-link'),
                ])->columns(2),

            Tabs::make('Languages')
                ->label(__('panel.common.languages'))
                ->tabs([
                    Tabs\Tab::make('tr')
                        ->label(__('panel.common.turkish'))
                        ->icon('heroicon-m-language')
                        ->schema([
                            TextInput::make('title.tr')
                                ->label(__('panel.menus.form.fields.title').' '.__('panel.common.tr_suffix'))
                                ->required(),
                        ]),
                    Tabs\Tab::make('en')
                        ->label(__('panel.common.english'))
                        ->icon('heroicon-m-language')
                        ->schema([
                            TextInput::make('title.en')
                                ->label(__('panel.menus.form.fields.title').' '.__('panel.common.en_suffix')),
                        ]),
                ])->columnSpanFull(),
        ]);
    }
}
