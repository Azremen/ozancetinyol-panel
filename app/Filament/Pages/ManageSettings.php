<?php

namespace App\Filament\Pages;

use App\Models\Page as PageModel;
use App\Models\Setting;
use App\Models\User;
use App\Support\UploadRules;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class ManageSettings extends Page
{
    // This property holds the form state (data)
    public ?array $data = [];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole(['Admin', 'admin', 'Developer', 'developer']);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('physio.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.common.general_settings');
    }

    public function getTitle(): string
    {
        return __('panel.common.general_settings');
    }

    public function mount(): void
    {
        // Fetch all settings and fill the form property
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('SettingsTabs')
                    ->tabs([
                        Tabs\Tab::make(__('panel.common.general_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Fieldset::make(__('panel.common.site_name'))
                                    ->schema([
                                        TextInput::make('site_name.tr')->label(__('panel.common.turkish')),
                                        TextInput::make('site_name.en')->label(__('panel.common.english')),
                                    ]),
                                Fieldset::make(__('panel.common.default_language'))
                                    ->schema([
                                        Select::make('default_language')
                                            ->label(__('panel.common.default_language'))
                                            ->options([
                                                'en' => 'English',
                                                'tr' => 'Türkçe',
                                            ])
                                            ->default('tr')
                                            ->helperText(__('panel.common.default_language_helper')),
                                    ]),
                                Fieldset::make(__('panel.common.address'))
                                    ->schema([
                                        Textarea::make('address.tr')->label(__('panel.common.turkish'))->rows(3),
                                        Textarea::make('address.en')->label(__('panel.common.english'))->rows(3),
                                        TextInput::make('address_locality')
                                            ->label(__('panel.common.city_district'))
                                            ->placeholder(__('panel.common.city_district_example'))
                                            ->helperText(__('panel.common.city_district_helper')),
                                        TextInput::make('postal_code')
                                            ->label(__('panel.common.postal_code'))
                                            ->maxLength(20),
                                        TextInput::make('latitude')
                                            ->label(__('panel.common.latitude'))
                                            ->numeric()
                                            ->minValue(-90)
                                            ->maxValue(90),
                                        TextInput::make('longitude')
                                            ->label(__('panel.common.longitude'))
                                            ->numeric()
                                            ->minValue(-180)
                                            ->maxValue(180),
                                    ]),
                                Fieldset::make(__('panel.common.homepage_map'))
                                    ->schema([
                                        Textarea::make('home_map_embed_url')
                                            ->label(__('panel.common.map_embed_url'))
                                            ->helperText(__('panel.common.map_embed_url_helper'))
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->dehydrateStateUsing(fn (?string $state): ?string => PageModel::normalizeMapEmbedUrl($state))
                                            ->rule(function () {
                                                return function (string $attribute, $value, \Closure $fail): void {
                                                    if (blank($value)) {
                                                        return;
                                                    }

                                                    if (! PageModel::normalizeMapEmbedUrl((string) $value)) {
                                                        $fail(__('panel.pages.form.fields.map_embed_validation'));
                                                    }
                                                };
                                            }),
                                    ]),
                                Fieldset::make(__('panel.common.logo'))
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label(__('panel.common.logo'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)
                                            ->disk('public')
                                            ->directory('settings')
                                            ->helperText(__('panel.common.logo_help'))
                                            ->columnSpanFull(),
                                    ]),
                                Fieldset::make(__('panel.common.favicon'))
                                    ->schema([
                                        FileUpload::make('favicon')
                                            ->label(__('panel.common.favicon'))
                                            ->acceptedFileTypes(UploadRules::FAVICON_MIME_TYPES)
                                            ->maxSize(UploadRules::MAX_FAVICON_KILOBYTES)
                                            ->disk('public')
                                            ->directory('settings')
                                            ->helperText(__('panel.common.favicon_help'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('panel.common.contact'))
                            ->icon('heroicon-o-phone')
                            ->schema([
                                TextInput::make('phone.tr')
                                    ->label(__('panel.common.phone_number'))
                                    ->tel()
                                    ->placeholder('+90 555 555 55 55'),
                                TextInput::make('email.tr')
                                    ->label(__('panel.common.contact_email'))
                                    ->email()
                                    ->placeholder('name@example.com'),
                                TextInput::make('mail_sender_name.tr')
                                    ->label(__('panel.common.mail_sender_name'))
                                    ->placeholder('Ozan Çetinyol'),
                            ]),
                        Tabs\Tab::make(__('panel.common.social_media'))
                            ->icon('heroicon-o-share')
                            ->schema([
                                TextInput::make('instagram_url.tr')
                                    ->label('Instagram')
                                    ->url()
                                    ->placeholder('https://instagram.com/'),
                                TextInput::make('facebook_url.tr')
                                    ->label('Facebook')
                                    ->url()
                                    ->placeholder('https://facebook.com/'),
                                TextInput::make('twitter_url.tr')
                                    ->label('Twitter / X')
                                    ->url()
                                    ->placeholder('https://x.com/')
                                    ->columnSpanFull(),
                                TextInput::make('whatsapp_url.tr')
                                    ->label('WhatsApp Chat URL')
                                    ->url()
                                    ->placeholder('https://wa.me/905551112233')
                                    ->columnSpanFull(),
                            ])->columns(2),
                        Tabs\Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                TextInput::make('recaptcha_site_key')
                                    ->label('Google reCAPTCHA Site Key')
                                    ->password(),
                                TextInput::make('recaptcha_secret_key')
                                    ->label('Google reCAPTCHA Secret Key')
                                    ->password(),
                                Select::make('recaptcha_enabled')
                                    ->label('reCAPTCHA (Enable / Disable)')
                                    ->options([
                                        '1' => __('panel.common.enabled'),
                                        '0' => __('panel.common.disabled'),
                                    ])
                                    ->default('0'),
                                Textarea::make('blocked_ips')
                                    ->label(__('panel.common.blocked_ips'))
                                    ->helperText(__('panel.common.blocked_ips_help'))
                                    ->rows(4),
                            ])->columns(1),
                        Tabs\Tab::make(__('panel.settings.seo_settings'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Fieldset::make(__('panel.common.home_seo_title'))
                                    ->schema([
                                        TextInput::make('home_seo_title.tr')
                                            ->label(__('panel.common.turkish'))
                                            ->helperText(__('panel.common.home_seo_title_helper')),
                                        TextInput::make('home_seo_title.en')
                                            ->label(__('panel.common.english'))
                                            ->helperText(__('panel.common.home_seo_title_helper')),
                                    ]),
                                Fieldset::make(__('panel.common.home_seo_description'))
                                    ->schema([
                                        Textarea::make('home_seo_description.tr')
                                            ->label(__('panel.common.turkish'))
                                            ->helperText(__('panel.common.home_seo_description_helper'))
                                            ->maxLength(160)
                                            ->rows(3),
                                        Textarea::make('home_seo_description.en')
                                            ->label(__('panel.common.english'))
                                            ->helperText(__('panel.common.home_seo_description_helper'))
                                            ->maxLength(160)
                                            ->rows(3),
                                    ]),
                                Fieldset::make(__('panel.common.site_description'))
                                    ->schema([
                                        Textarea::make('site_description.tr')
                                            ->label(__('panel.common.turkish'))
                                            ->helperText(__('panel.common.site_description_helper'))
                                            ->rows(3),
                                        Textarea::make('site_description.en')
                                            ->label(__('panel.common.english'))
                                            ->helperText(__('panel.common.site_description_helper'))
                                            ->rows(3),
                                    ]),
                                Fieldset::make(__('panel.common.og_image'))
                                    ->schema([
                                        FileUpload::make('og_image')
                                            ->label(__('panel.common.og_image'))
                                            ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)
                                            ->disk('public')
                                            ->directory('settings')
                                            ->helperText('Recommended 1200×630 px. Used for Facebook, WhatsApp and Twitter shares.')
                                            ->columnSpanFull(),
                                    ]),
                                Fieldset::make(__('panel.common.address_country'))
                                    ->schema([
                                        TextInput::make('address_country')
                                            ->label(__('panel.common.address_country'))
                                            ->placeholder('TR')
                                            ->maxLength(10),
                                        TextInput::make('google_business_url')
                                            ->label('Google Business Profile URL')
                                            ->url()
                                            ->columnSpanFull(),
                                    ]),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data'); // Property to bind form variables
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
        $data = $this->form->getState();

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
