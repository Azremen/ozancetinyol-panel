<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('TestimonialTabs')
                    ->tabs([
                        Tabs\Tab::make(__('panel.common.turkish'))
                            ->schema([
                                Textarea::make('content.tr')->label(__('panel.common.review').' (TR)')->required(),
                            ]),
                        Tabs\Tab::make(__('panel.common.english'))
                            ->schema([
                                Textarea::make('content.en')->label(__('panel.common.review').' (EN)'),
                            ]),
                        Tabs\Tab::make(__('panel.common.info'))
                            ->schema([
                                TextInput::make('client_name')->label(__('panel.common.client_name'))->required(),
                                TextInput::make('client_title')->label(__('panel.common.client_title'))->placeholder(__('panel.common.guest_title')),
                                Select::make('rating')->label(__('panel.common.rating'))->options([
                                    1 => '1 '.__('panel.common.star'),
                                    2 => '2 '.__('panel.common.stars'),
                                    3 => '3 '.__('panel.common.stars'),
                                    4 => '4 '.__('panel.common.stars'),
                                    5 => '5 '.__('panel.common.stars'),
                                ])->default(5)->required(),
                                TextInput::make('order')->label(__('panel.common.order'))->numeric()->default(0),
                                Toggle::make('is_active')->label(__('panel.common.active'))->default(true),
                                FileUpload::make('image')
                                    ->label(__('panel.common.avatar_image'))
                                    ->image()
                                    ->directory('testimonials')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
