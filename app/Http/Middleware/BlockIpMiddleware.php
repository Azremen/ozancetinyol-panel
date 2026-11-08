<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockIpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $blockedIps = $this->blockedIps();

        if ($blockedIps !== [] && $this->isBlocked($request->ip(), $blockedIps)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }

    /**
     * Parse the comma/whitespace separated "blocked_ips" setting.
     *
     * @return list<string>
     */
    private function blockedIps(): array
    {
        try {
            $value = Setting::get('blocked_ips');
        } catch (\Throwable) {
            return [];
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value) ?: [])));
    }

    /**
     * @param  list<string>  $blockedIps
     */
    private function isBlocked(string $ip, array $blockedIps): bool
    {
        $ip = $this->normalizeIp($ip);

        foreach ($blockedIps as $blocked) {
            if ($ip === $this->normalizeIp($blocked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize an IPv4-mapped IPv6 address (::ffff:1.2.3.4) to its IPv4 form,
     * so both representations are treated equally.
     */
    private function normalizeIp(string $ip): string
    {
        if (str_starts_with($ip, '::ffff:') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipv4 = substr($ip, 7);

            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ipv4;
            }
        }

        return $ip;
    }
}
