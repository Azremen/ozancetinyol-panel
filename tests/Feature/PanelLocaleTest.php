<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Livewire\LanguageSwitcher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PanelLocaleTest extends TestCase
{
    public function test_initial_locale_uses_browser_preferences_with_turkish_fallback(): void
    {
        foreach ([
            'tr-TR,tr;q=0.9,en;q=0.8' => 'tr',
            'en-US,en;q=0.9,tr;q=0.8' => 'en',
            'tr;q=0.5,en;q=0.9' => 'en',
            'de-DE,de;q=0.9' => 'tr',
            'de-DE,en-GB;q=0.8' => 'en',
            '' => 'tr',
        ] as $header => $expected) {
            session()->forget('locale');
            $request = Request::create('/login');
            $request->headers->set('Accept-Language', $header);
            $request->setLaravelSession(session()->driver());

            $response = (new SetLocale)->handle($request, fn (): Response => new Response(app()->getLocale()));

            $this->assertSame($expected, $response->getContent());
            $this->assertFalse(session()->has('locale'));
        }
    }

    public function test_valid_session_selection_overrides_browser_and_invalid_selection_is_ignored(): void
    {
        foreach (['tr' => 'tr', 'en' => 'en', 'fr' => 'en'] as $selection => $expected) {
            session()->put('locale', $selection);
            $request = Request::create('/login');
            $request->headers->set('Accept-Language', 'en-US');
            $request->setLaravelSession(session()->driver());

            $response = (new SetLocale)->handle($request, fn (): Response => new Response(app()->getLocale()));

            $this->assertSame($expected, $response->getContent());
        }
    }

    public function test_language_switcher_saves_only_supported_languages(): void
    {
        $switcher = new LanguageSwitcher;
        $switcher->switchLanguage('tr');
        $this->assertSame('tr', session('locale'));

        try {
            $switcher->switchLanguage('fr');
            $this->fail('Unsupported locale should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('locale', $exception->errors());
            $this->assertSame('tr', session('locale'));
        }
    }
}
