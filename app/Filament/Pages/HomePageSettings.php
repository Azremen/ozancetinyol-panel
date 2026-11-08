<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\UploadRules;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HomePageSettings extends Page
{
    public const SECTIONS = ['slider', 'about', 'services', 'testimonials', 'news', 'location', 'appointment'];

    public ?array $data = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-home-modern';
    }

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('physio.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.common.home_page_settings');
    }

    public function getTitle(): string
    {
        return __('panel.common.home_page_settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $settings['home_sections'] ??= array_map(fn ($section) => ['section' => $section, 'enabled' => true], self::SECTIONS);
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('HomePageTabs')
                    ->tabs([
                        Tabs\Tab::make(__('cms.sections'))
                            ->schema([
                                Repeater::make('home_sections')
                                    ->label(__('cms.sections'))
                                    ->helperText(__('cms.sections_help'))
                                    ->schema([
                                        Select::make('section')->label(__('cms.section'))
                                            ->options(array_combine(self::SECTIONS, array_map(fn ($id) => __('cms.section_'.$id), self::SECTIONS)))
                                            ->required(),
                                        Toggle::make('enabled')->label(__('cms.visible'))->default(true)->required(),
                                    ])
                                    ->addable(false)->deletable(false)->reorderable()
                                    ->minItems(7)->maxItems(7),
                            ]),
                        Tabs\Tab::make(__('panel.common.slider_fallback'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Fieldset::make(__('panel.common.subtitle'))
                                    ->schema([
                                        TextInput::make('home_slider_1_subtitle.tr')->label(__('panel.common.subtitle').' (TR)'),
                                        TextInput::make('home_slider_1_subtitle.en')->label(__('panel.common.subtitle').' (EN)')->default('Physiotherapy and rehabilitation'),
                                    ])->columns(2),
                                Fieldset::make(__('panel.common.title'))
                                    ->schema([
                                        TextInput::make('home_slider_1_title.tr')->label(__('panel.common.title').' (TR)'),
                                        TextInput::make('home_slider_1_title.en')->label(__('panel.common.title').' (EN)')->default('Move with confidence'),
                                    ])->columns(2),
                                Fieldset::make(__('panel.common.image'))
                                    ->schema([
                                        FileUpload::make('home_slider_1_image')
                                            ->label(__('panel.common.image'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)->disk('public')
                                            ->directory('settings'),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('panel.common.about_us'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Fieldset::make(__('panel.common.subtitle'))
                                    ->schema([
                                        TextInput::make('home_about_subtitle.tr')->label(__('panel.common.subtitle').' (TR)'),
                                        TextInput::make('home_about_subtitle.en')->label(__('panel.common.subtitle').' (EN)'),
                                    ]),
                                Fieldset::make(__('panel.common.title'))
                                    ->schema([
                                        TextInput::make('home_about_title.tr')->label(__('panel.common.title').' (TR)'),
                                        TextInput::make('home_about_title.en')->label(__('panel.common.title').' (EN)'),
                                    ]),
                                Fieldset::make(__('panel.common.images'))
                                    ->schema([
                                        FileUpload::make('home_about_image_1')
                                            ->label(__('panel.common.image_1'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)->disk('public')
                                            ->directory('settings'),
                                        FileUpload::make('home_about_image_2')
                                            ->label(__('panel.common.image_2'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)->disk('public')
                                            ->directory('settings'),
                                    ]),
                                Fieldset::make(__('panel.common.content'))
                                    ->schema([
                                        RichEditor::make('home_about_content.tr')->label(__('panel.common.content').' (TR)')->columnSpanFull(),
                                        RichEditor::make('home_about_content.en')->label(__('panel.common.content').' (EN)')->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('panel.common.titles_subtitles'))
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Fieldset::make(__('physio.services'))
                                    ->schema([
                                        TextInput::make('home_services_subtitle.tr')->label(__('panel.common.subtitle').' (TR)'),
                                        TextInput::make('home_services_title.tr')->label(__('panel.common.title').' (TR)'),
                                        TextInput::make('home_services_subtitle.en')->label(__('panel.common.subtitle').' (EN)'),
                                        TextInput::make('home_services_title.en')->label(__('panel.common.title').' (EN)'),
                                    ]),
                                Fieldset::make(__('panel.common.testimonials'))
                                    ->schema([
                                        TextInput::make('home_testimonials_subtitle.tr')->label(__('panel.common.subtitle').' (TR)'),
                                        TextInput::make('home_testimonials_title.tr')->label(__('panel.common.title').' (TR)'),
                                        TextInput::make('home_testimonials_subtitle.en')->label(__('panel.common.subtitle').' (EN)'),
                                        TextInput::make('home_testimonials_title.en')->label(__('panel.common.title').' (EN)'),
                                        FileUpload::make('home_testimonials_background')
                                            ->label(__('panel.common.background_image'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)->disk('public')
                                            ->directory('settings')
                                            ->columnSpanFull(),
                                    ]),
                                Fieldset::make(__('panel.common.news_blog'))
                                    ->schema([
                                        TextInput::make('home_news_subtitle.tr')->label(__('panel.common.subtitle').' (TR)'),
                                        TextInput::make('home_news_title.tr')->label(__('panel.common.title').' (TR)'),
                                        TextInput::make('home_news_subtitle.en')->label(__('panel.common.subtitle').' (EN)'),
                                        TextInput::make('home_news_title.en')->label(__('panel.common.title').' (EN)'),
                                    ]),
                            ]),

                        Tabs\Tab::make(__('panel.common.footer_settings'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Fieldset::make(__('panel.common.footer_about'))
                                    ->schema([
                                        Textarea::make('footer_about_content.tr')->label(__('panel.common.content').' (TR)')->columnSpanFull(),
                                        Textarea::make('footer_about_content.en')->label(__('panel.common.content').' (EN)')->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                EmbeddedSchema::make('form'),
            ])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('panel.common.save'))
                            ->submit('save'),
                    ])
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        Validator::make(['data' => $data], [
            'data.home_sections' => ['required', 'array', 'size:7'],
            'data.home_sections.*.section' => ['required', Rule::in(self::SECTIONS), 'distinct:strict'],
            'data.home_sections.*.enabled' => ['required', 'boolean'],
        ], [
            'data.home_sections.*' => __('cms.sections_error'),
            'data.home_sections.*.section.*' => __('cms.sections_error'),
            'data.home_sections.*.enabled.*' => __('cms.sections_error'),
        ])->validate();
        $data['home_sections'] = array_values(array_map(fn ($row) => ['section' => $row['section'], 'enabled' => (bool) $row['enabled']], $data['home_sections']));
        DB::transaction(function () use ($data): void {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => 'text',
                    ]
                );
            }
        });

        foreach (array_keys($data) as $key) {
            Setting::forgetCache($key);
        }

        Notification::make()
            ->success()
            ->title(__('panel.common.settings_saved_successfully'))
            ->send();
    }
}
