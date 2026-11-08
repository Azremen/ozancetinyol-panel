<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Trusts only the proxies listed in TRUSTED_PROXIES (comma separated IPs/CIDRs,
 * or "*" when the app is exclusively reachable through a load balancer).
 * Without this, request()->ip() and isSecure() reflect the proxy, breaking
 * IP throttling/blocking and HTTPS-only headers behind nginx/Plesk.
 */
class TrustProxies extends Middleware
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $configured = config('app.trusted_proxies');

        if (is_array($configured)) {
            return $configured === [] ? null : array_values($configured);
        }

        $configured = trim((string) $configured);

        if ($configured === '') {
            return null;
        }

        if ($configured === '*' || $configured === '**') {
            return $configured;
        }

        $proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));

        return $proxies === [] ? null : $proxies;
    }
}
