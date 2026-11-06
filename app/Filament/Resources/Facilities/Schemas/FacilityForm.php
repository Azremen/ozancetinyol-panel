<?php

namespace App\Filament\Resources\Facilities\Schemas;

use App\Helpers\IconHelper;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class FacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('FacilityTabs')
                    ->tabs([
                        Tabs\Tab::make(__('panel.common.turkish'))
                            ->schema([
                                TextInput::make('title.tr')->label(__('panel.common.title').' (TR)')->required(),
                                Textarea::make('description.tr')->label(__('panel.common.description').' (TR)'),
                            ]),
                        Tabs\Tab::make(__('panel.common.english'))
                            ->schema([
                                TextInput::make('title.en')->label(__('panel.common.title').' (EN)'),
                                Textarea::make('description.en')->label(__('panel.common.description').' (EN)'),
                            ]),
                        Tabs\Tab::make(__('panel.common.settings'))
                            ->schema([
                                Select::make('icon')
                                    ->label(__('panel.common.icon_class'))
                                    ->searchable()
                                    ->allowHtml()
                                    ->options(IconHelper::getFlaticons()),
                                TextInput::make('order')->label(__('panel.common.order'))->numeric()->default(0),
                                Toggle::make('is_active')->label(__('panel.common.active'))->default(true),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
