<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Livewire\LanguageSwitcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkVerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CsrfCookieTest extends TestCase
{
    public function test_web_responses_set_httponly_csrf_and_session_cookies(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $response->assertCookie('XSRF-TOKEN');
        $response->assertCookie(config('session.cookie'));
        $this->assertTrue($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
        $this->assertTrue($response->getCookie(config('session.cookie'), false)->isHttpOnly());

        Route::middleware('web')->get('/_test/csrf-redirect', fn () => redirect('/login'));
        $redirect = $this->get('/_test/csrf-redirect')->assertRedirect('/login');
        $redirect->assertCookie('XSRF-TOKEN');
        $this->assertTrue($redirect->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }

    public function test_filament_login_sets_an_httponly_cookie_and_exposes_a_page_token(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
        });

        $response = $this->get('/login')->assertOk();

        $response->assertSee('name="csrf-token"', false);
        $response->assertCookie('XSRF-TOKEN');
        $this->assertTrue($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }

    public function test_only_httponly_changes_on_the_framework_csrf_cookie(): void
    {
        $this->freezeTime();
        $request = Request::create('https://panel.example.test/');
        $request->setLaravelSession(session()->driver());
        $request->session()->put('_token', 'csrf-cookie-regression-token');
        $config = array_replace(config('session'), [
            'path' => '/panel',
            'domain' => 'panel.example.test',
            'secure' => true,
            'same_site' => 'strict',
            'partitioned' => true,
        ]);
        $method = new \ReflectionMethod(FrameworkVerifyCsrfToken::class, 'newCookie');
        $frameworkCookie = $method->invoke(app(FrameworkVerifyCsrfToken::class), $request, $config);
        $cookie = (new \ReflectionMethod(VerifyCsrfToken::class, 'newCookie'))
            ->invoke(app(VerifyCsrfToken::class), $request, $config);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame((string) $frameworkCookie->withHttpOnly(true), (string) $cookie);
    }

    public function test_csrf_validation_still_accepts_form_and_header_tokens_and_rejects_missing_or_invalid_tokens(): void
    {
        // Laravel bypasses CSRF validation in the testing environment.
        app()->instance('env', 'local');
        Route::middleware('web')->post('/_test/csrf', fn () => response()->noContent());
        $token = 'csrf-validation-regression-token';
        $this->withSession(['_token' => $token]);

        $this->post('/_test/csrf')->assertStatus(419);
        $this->post('/_test/csrf', ['_token' => 'invalid'])->assertStatus(419);
        $response = $this->post('/_test/csrf', ['_token' => $token])->assertNoContent();
        $this->assertTrue($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
        $this->post('/_test/csrf', [], ['X-CSRF-TOKEN' => $token])->assertNoContent();
    }

    public function test_livewire_update_accepts_a_body_token_without_a_readable_xsrf_cookie(): void
    {
        $snapshot = Livewire::test(LanguageSwitcher::class)->snapshot;
        app()->instance('env', 'local');
        $token = 'livewire-csrf-regression-token';
        $this->withSession(['_token' => $token]);

        $this->postJson(Livewire::getUpdateUri(), ['components' => []], ['X-Livewire' => ''])
            ->assertStatus(419);
        $response = $this->postJson(Livewire::getUpdateUri(), [
            '_token' => $token,
            'components' => [[
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'switchLanguage', 'params' => ['en']]],
            ]],
        ], ['X-Livewire' => ''])->assertOk()->assertSessionHas('locale', 'en');

        $response->assertJsonCount(1, 'components');
        $this->assertTrue($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }
}
