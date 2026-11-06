<?php

namespace App\Filament\Resources\Sliders\Schemas;

use App\Models\Url;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.common.info'))
                    ->description(__('panel.sliders.form.description'))
                    ->schema([]),
                Section::make(__('panel.common.image'))->schema([
                    FileUpload::make('images')
                        ->label(__('panel.sliders.form.fields.image'))
                        ->image()
                        ->directory('sliders')
                        ->multiple()
                        ->reorderable()
                        ->required()
                        ->helperText(__('panel.sliders.form.fields.image_helper'))
                        // Resize on the client to target hero size without hard-cropping
                        ->automaticallyResizeImagesMode('contain')
                        ->automaticallyResizeImagesToWidth(1920)
                        ->automaticallyResizeImagesToHeight(700),
                ]),
                Section::make(__('panel.sliders.form.sections.english'))->schema([
                    Grid::make(3)->schema([
                        TextInput::make('title.en')->label(__('panel.sliders.form.fields.title_en')),
                        TextInput::make('subtitle.en')->label(__('panel.sliders.form.fields.subtitle_en')),
                        TextInput::make('button_text.en')->label(__('panel.sliders.form.fields.button_text_en')),
                    ]),
                ]),
                Section::make(__('panel.sliders.form.sections.turkish'))->schema([
                    Grid::make(3)->schema([
                        TextInput::make('title.tr')->label(__('panel.sliders.form.fields.title_tr')),
                        TextInput::make('subtitle.tr')->label(__('panel.sliders.form.fields.subtitle_tr')),
                        TextInput::make('button_text.tr')->label(__('panel.sliders.form.fields.button_text_tr')),
                    ]),
                ]),
                Section::make(__('panel.common.settings'))->schema([
                    Grid::make(3)->schema([
                        Select::make('button_link')
                            ->label(__('panel.sliders.form.fields.button_link'))
                            ->options(function () {
                                $urls = Url::pluck('slug', 'slug')->toArray();
                                $scrollTargets = [
                                    '#about' => __('panel.sliders.sections.about'),
                                    '#services' => __('physio.services_title'),
                                    '#news' => __('panel.sliders.sections.news'),
                                    '#testimonials' => __('panel.sliders.sections.testimonials'),
                                    '#appointment' => __('physio.appointment'),
                                    '#contact' => __('nav.contact'),
                                ];

                                return array_merge($scrollTargets, $urls);
                            })
                            ->searchable()
                            ->preload(),
                        TextInput::make('order')
                            ->label(__('panel.sliders.form.fields.order'))
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('panel.sliders.form.fields.is_active'))
                            ->default(true),
                    ]),
                ]),
            ]);
    }
}
