<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Add any specific API endpoints that shouldn't have CSRF protection
        'api/*',
        'webhooks/*',
        'product-requests',
        'product-requests/*',
        'product-requests/create',
        'product-requests/store',
        'product-requests/submit',
        // DocuSeal integration endpoints for testing and API calls
        'quick-requests/docuseal/generate-submission-slug',
        'quick-requests/docuseal/generate-builder-token',
        'quick-requests/docuseal/generate-form-token',
    ];
}
