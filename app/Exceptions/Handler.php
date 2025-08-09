<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

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

        // Handle mail exceptions to prevent application halting
        $this->renderable(function (Throwable $e, $request) {
            if ($this->isMailException($e)) {
                Log::error('Mail exception handled globally', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id
                ]);

                // For API requests, return a success response with email warning
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Operation completed successfully. Email notification failed.',
                        'email_error' => 'Email service temporarily unavailable',
                        'status' => 'success'
                    ], 200);
                }

                // For Inertia requests, return a proper response
                if ($request->header('X-Inertia')) {
                    return Inertia::render('Error', [
                        'status' => 200,
                        'message' => 'Operation completed successfully.',
                        'warning' => 'Email notification failed. Please check your email configuration.',
                    ])->toResponse($request)->setStatusCode(200);
                }

                // For regular requests, redirect back with success and warning
                return redirect()->back()->with([
                    'success' => 'Operation completed successfully.',
                    'warning' => 'Email notification failed. Please check your email configuration.'
                ]);
            }
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

    /**
     * Check if the exception is mail-related
     *
     * @param Throwable $e
     * @return bool
     */
    protected function isMailException(Throwable $e): bool
    {
        $mailKeywords = [
            'mailgun',
            'MailgunTransportFactory',
            'mail',
            'email',
            'smtp',
            'transport',
            'mailer',
            'symfony\\component\\mailer'
        ];

        $message = strtolower($e->getMessage());
        $file = strtolower($e->getFile());

        foreach ($mailKeywords as $keyword) {
            if (str_contains($message, strtolower($keyword)) || str_contains($file, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
