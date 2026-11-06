<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('PostTabs')
                    ->tabs([
                        Tabs\Tab::make(__('panel.common.turkish'))
                            ->schema([
                                TextInput::make('title.tr')->label(__('panel.common.title').' (TR)')->required(),
                                TextInput::make('category_name.tr')->label(__('panel.common.category_name').' (TR)'),
                            ]),
                        Tabs\Tab::make(__('panel.common.english'))
                            ->schema([
                                TextInput::make('title.en')->label(__('panel.common.title').' (EN)'),
                                TextInput::make('category_name.en')->label(__('panel.common.category_name').' (EN)'),
                            ]),
                        Tabs\Tab::make(__('panel.common.settings_media'))
                            ->schema([
                                DatePicker::make('publish_date')
                                    ->label(__('panel.common.publish_date'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                Toggle::make('is_active')->label(__('panel.common.active'))->default(true),
                                FileUpload::make('image')
                                    ->label(__('panel.common.image'))
                                    ->image()
                                    ->directory('posts')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
