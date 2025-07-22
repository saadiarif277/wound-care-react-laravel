<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureCookies
{
    /**
     * Handle an incoming request and ensure cookies have proper security attributes.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only modify cookies if we have a response with cookies
        if ($response->headers->has('Set-Cookie')) {
            $cookies = $response->headers->all()['set-cookie'] ?? [];
            $modifiedCookies = [];

            foreach ($cookies as $cookie) {
                $modifiedCookie = $this->modifyCookieAttributes($cookie, $request);
                $modifiedCookies[] = $modifiedCookie;
            }

            // Replace all cookies with modified versions
            $response->headers->remove('Set-Cookie');
            foreach ($modifiedCookies as $cookie) {
                $response->headers->set('Set-Cookie', $cookie, false);
            }
        }

        return $response;
    }

    /**
     * Modify cookie attributes based on the request security.
     *
     * @param string $cookie
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    private function modifyCookieAttributes(string $cookie, Request $request): string
    {
        $isSecure = $request->secure() || $request->getScheme() === 'https';
        
        // Parse cookie attributes
        $hasSecure = stripos($cookie, 'secure') !== false;
        $hasSameSite = stripos($cookie, 'samesite') !== false;
        $hasHttpOnly = stripos($cookie, 'httponly') !== false;

        // If request is secure and cookie doesn't have secure flag, add it
        if ($isSecure && !$hasSecure) {
            $cookie .= '; secure';
        }
        // If request is not secure and cookie has secure flag, remove it (for local dev)
        elseif (!$isSecure && $hasSecure) {
            $cookie = preg_replace('/;\s*secure/i', '', $cookie);
        }

        // Ensure SameSite attribute is present
        if (!$hasSameSite) {
            $cookie .= '; samesite=' . config('session.same_site', 'lax');
        }

        // Ensure HttpOnly is present for session cookies
        if (!$hasHttpOnly && $this->isSessionCookie($cookie)) {
            $cookie .= '; httponly';
        }

        return $cookie;
    }

    /**
     * Check if the cookie is a session cookie.
     *
     * @param string $cookie
     * @return bool
     */
    private function isSessionCookie(string $cookie): bool
    {
        $sessionName = config('session.cookie');
        return strpos($cookie, $sessionName . '=') === 0;
    }
} 