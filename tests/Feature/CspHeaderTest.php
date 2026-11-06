<?php

namespace Tests\Feature;

use App\Http\Middleware\CspMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CspHeaderTest extends TestCase
{
    public function test_loopback_sources_are_only_allowed_locally(): void
    {
        foreach (['local' => true, 'production' => false] as $environment => $allowed) {
            app()->instance('env', $environment);
            $response = (new CspMiddleware)->handle(Request::create('/'), fn (): Response => new Response);
            $policy = $response->headers->get('Content-Security-Policy');

            $this->assertNotNull($policy);
            $this->assertSame($allowed, str_contains($policy, 'http://127.0.0.1:*'));
            $this->assertSame($allowed, str_contains($policy, 'ws://127.0.0.1:*'));
            preg_match('/connect-src ([^;]+)/', $policy, $connections);
            $this->assertNotContains('https:', explode(' ', $connections[1]));
            $this->assertNotContains('wss:', explode(' ', $connections[1]));
        }
    }

    public function test_errors_have_security_headers(): void
    {
        $response = $this->get('/missing-panel-route')->assertNotFound();

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_csrf_and_session_cookie_flags_are_preserved(): void
    {
        $response = (new CspMiddleware)->handle(Request::create('/login'), function (): Response {
            $response = new Response;
            $response->headers->setCookie(Cookie::create('XSRF-TOKEN', 'token')->withHttpOnly(false));
            $response->headers->setCookie(Cookie::create('session', 'session')->withHttpOnly(true)->withSecure(true));

            return $response;
        });

        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);
        $this->assertFalse($cookies[0]->isHttpOnly());
        $this->assertTrue($cookies[1]->isHttpOnly());
        $this->assertTrue($cookies[1]->isSecure());
    }

    public function test_http_pages_do_not_upgrade_asset_requests(): void
    {
        $request = Request::create('http://ozancetinyolpanel.test/login');
        $response = (new CspMiddleware)->handle($request, fn (): Response => new Response);

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $policy);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_https_pages_upgrade_asset_requests(): void
    {
        $request = Request::create('https://ozancetinyolpanel.test/login');
        $response = (new CspMiddleware)->handle($request, fn (): Response => new Response);

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString('upgrade-insecure-requests', $policy);
        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_cloudflare_analytics_script_is_allowed(): void
    {
        $request = Request::create('/');
        $response = (new CspMiddleware)->handle($request, fn (): Response => new Response);

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString(
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://static.cloudflareinsights.com;",
            $policy
        );
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function test_successful_image_responses_keep_their_immutable_cache_policy(): void
    {
        $request = Request::create('/img/100/100');
        $response = new Response;
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();

        (new CspMiddleware)->handle($request, fn (): Response => $response);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }
}
