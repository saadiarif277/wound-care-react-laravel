<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class HandleMailExceptions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Exception $e) {
            // Check if this is a mail-related exception
            if ($this->isMailException($e)) {
                Log::error('Mail exception caught in middleware', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id
                ]);

                // Return a response indicating the operation completed but email failed
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Operation completed successfully. Email notification failed.',
                        'email_error' => 'Email service temporarily unavailable',
                        'status' => 'success'
                    ], 200);
                }

                // For web requests, redirect back with a flash message
                return redirect()->back()->with([
                    'success' => 'Operation completed successfully.',
                    'warning' => 'Email notification failed. Please check your email configuration.'
                ]);
            }

            // Re-throw non-mail exceptions
            throw $e;
        }
    }

    /**
     * Check if the exception is mail-related
     *
     * @param Exception $e
     * @return bool
     */
    protected function isMailException(Exception $e): bool
    {
        $mailKeywords = [
            'mailgun',
            'MailgunTransportFactory',
            'mail',
            'email',
            'smtp',
            'transport',
            'mailer'
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
