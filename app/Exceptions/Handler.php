<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Inertia\Inertia;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle CSRF token mismatch exceptions
        $this->renderable(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'CSRF token mismatch',
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                    'status' => 419,
                    'requires_refresh' => true,
                ], 419);
            }

            // For Inertia requests, return a proper response
            if ($request->header('X-Inertia')) {
                return Inertia::render('Error', [
                    'status' => 419,
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                    'requires_refresh' => true,
                ])->toResponse($request)->setStatusCode(419);
            }

            // For regular requests, redirect with error message
            return redirect()->back()->withErrors([
                'csrf' => 'Your session has expired. Please refresh the page and try again.',
            ]);
        });

        // Handle validation exceptions
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $e->errors(),
                    'status' => 422,
                ], 422);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::render('Error', [
                    'status' => 422,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ])->toResponse($request)->setStatusCode(422);
            }
        });

        // Handle authentication exceptions
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Please log in to continue.',
                    'status' => 401,
                ], 401);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::render('Error', [
                    'status' => 401,
                    'message' => 'Please log in to continue.',
                ])->toResponse($request)->setStatusCode(401);
            }
        });
    }
}
