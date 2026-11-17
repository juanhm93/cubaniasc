<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers for every response. The shared hosting gives no
 * control over the Apache config, so they are set here instead.
 */
class SecurityHeaders
{
    /**
     * One year, without includeSubDomains: other subdomains of the hosting
     * account (webmail, cpanel) are not under this app's control.
     */
    private const HSTS_MAX_AGE_SECONDS = 31536000;

    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && ! app()->environment(['local', 'testing'])) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_MAX_AGE_SECONDS);
        }

        return $response;
    }
}
