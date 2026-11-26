<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_forbidden_response_uses_the_branded_error_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/errors/forbidden', fn () => abort(403));

        $this->get('/_test/errors/forbidden')
            ->assertForbidden()
            ->assertSeeText(__('errors.access_denied'))
            ->assertSeeText(__('errors.back_to_panel'));
    }

    public function test_not_found_response_uses_the_branded_error_page(): void
    {
        config(['app.debug' => false]);

        $this->get('/_test/errors/not-found')
            ->assertNotFound()
            ->assertSeeText(__('errors.page_not_found'))
            ->assertSeeText(__('errors.back_to_panel'));
    }

    public function test_error_responses_use_the_branded_page_in_both_languages(): void
    {
        config(['app.debug' => false]);
        $errors = [
            401 => 'errors.unauthorized',
            402 => 'errors.client_error',
            403 => 'errors.access_denied',
            404 => 'errors.page_not_found',
            419 => 'errors.session_expired',
            429 => 'errors.too_many_requests',
            500 => 'errors.internal_server_error',
            503 => 'errors.service_unavailable',
            405 => 'errors.client_error',
            502 => 'errors.internal_server_error',
        ];

        foreach ($errors as $code => $title) {
            Route::get('/_test/error-'.$code, fn () => abort($code, 'private error detail'));
        }

        foreach (['tr', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ($errors as $code => $title) {
                $this->get('/_test/error-'.$code)
                    ->assertStatus($code)
                    ->assertSeeText(__($title))
                    ->assertSee('css/errors.css')
                    ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                    ->assertDontSee('private error detail');
            }
        }
    }

    public function test_maintenance_view_can_render_without_an_exception(): void
    {
        $this->view('errors.503')
            ->assertSeeText(__('errors.service_unavailable'));
    }

    public function test_json_errors_and_retry_headers_are_preserved(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/throttled', fn () => abort(429, 'Too many requests', ['Retry-After' => '60']));

        $this->get('/_test/throttled')->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->getJson('/_test/throttled')->assertStatus(429)->assertJsonPath('message', 'Too many requests');
    }

    public function test_unexpected_exceptions_use_the_500_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/unexpected', fn () => throw new \RuntimeException('private error detail'));

        $this->get('/_test/unexpected')
            ->assertStatus(500)
            ->assertSeeText(__('errors.internal_server_error'))
            ->assertDontSee('private error detail');
    }
}
