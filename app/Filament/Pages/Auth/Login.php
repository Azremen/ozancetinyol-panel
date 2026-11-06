<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    private const EMAIL_MAX_ATTEMPTS = 5;

    private const IP_MAX_ATTEMPTS = 20;

    private const DECAY_SECONDS = 900;

    public function authenticate(): ?LoginResponse
    {
        $email = Str::lower(trim((string) data_get($this->data, 'email')));
        $ip = request()->ip() ?? 'unknown';
        $emailKey = $this->emailRateLimitKey($ip, $email);
        $ipKey = $this->ipRateLimitKey($ip);

        if (
            RateLimiter::tooManyAttempts($emailKey, self::EMAIL_MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_ATTEMPTS)
        ) {
            $this->throwRateLimitValidationException();
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            RateLimiter::hit($emailKey, self::DECAY_SECONDS);
            RateLimiter::hit($ipKey, self::DECAY_SECONDS);

            throw $exception;
        }

        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);

        return $response;
    }

    private function emailRateLimitKey(string $ip, string $email): string
    {
        return 'admin-login:email:'.hash('sha256', $ip.'|'.$email);
    }

    private function ipRateLimitKey(string $ip): string
    {
        return 'admin-login:ip:'.hash('sha256', $ip);
    }

    private function throwRateLimitValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
