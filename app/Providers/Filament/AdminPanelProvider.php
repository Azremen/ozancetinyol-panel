<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AppointmentSettings;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\HomePageSettings;
use App\Filament\Pages\LegalPageSettings;
use App\Filament\Pages\ManageSettings;
use App\Http\Middleware\NoIndexMiddleware;
use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->assets([
                Css::make('appointment-calendar', asset('css/appointment-calendar.css')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                HomePageSettings::class,
                ManageSettings::class,
                AppointmentSettings::class,
                LegalPageSettings::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                NoIndexMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => Blade::render('@livewire(\'language-switcher\')')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                function (): string {
                    $favicon = $this->resolveFaviconUrl();

                    $tags = [
                        '<meta name="robots" content="noindex, nofollow">',
                        '<link rel="apple-touch-icon" sizes="180x180" href="'.asset('apple-touch-icon.png').'">',
                        '<link rel="manifest" href="'.asset('site.webmanifest').'">',
                        '<meta name="theme-color" content="#517b7a">',
                    ];

                    if ($favicon !== null) {
                        $tags[] = '<link rel="icon" href="'.$favicon.'">';
                    } else {
                        $tags[] = '<link rel="icon" type="image/x-icon" href="'.asset('favicon.ico').'">';
                        $tags[] = '<link rel="icon" type="image/png" sizes="32x32" href="'.asset('favicon-32x32.png').'">';
                        $tags[] = '<link rel="icon" type="image/png" sizes="16x16" href="'.asset('favicon-16x16.png').'">';
                    }

                    return implode("\n", $tags);
                }
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => implode("\n", [
                    '<link rel="stylesheet" href="'.asset('frontend/css/plugins/flaticon.css').'" />',
                    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha384-h/hnnw1Bi4nbpD6kE7nYfCXzovi622sY5WBxww8ARKwpdLj5kUWjRuyiXaD1U2JT" crossorigin="anonymous" referrerpolicy="no-referrer" />',
                ])
            );
    }

    private function resolveFaviconUrl(): ?string
    {
        $favicon = Setting::get('favicon');

        if (! is_string($favicon) || trim($favicon) === '') {
            return null;
        }

        $favicon = trim($favicon);

        if (Str::startsWith($favicon, ['http://', 'https://'])) {
            return $favicon;
        }

        $normalizedPath = ltrim(str_replace('public/', '', $favicon), '/');

        return asset('storage/'.$normalizedPath);
    }
}
