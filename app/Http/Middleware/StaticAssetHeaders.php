<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticAssetHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get the request path
        $path = $request->path();

        // Check if this is a static asset
        if ($this->isStaticAsset($path)) {
            // Add cache headers for static assets
            $maxAge = config('static-assets.cache_max_age', 31536000);
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}, immutable");

            // Add security headers
            foreach (config('static-assets.security_headers', []) as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    /**
     * Determine if the path is for a static asset
     */
    private function isStaticAsset(string $path): bool
    {
        $staticExtensions = config('static-assets.extensions', []);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $staticExtensions);
    }
}
