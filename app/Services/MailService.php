<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Mail\Message;

class MailService
{
    /**
     * Send email with exception handling
     *
     * @param string|array $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param callable|null $callback
     * @return bool
     */
    public static function send($to, string $subject, string $view, array $data = [], callable $callback = null): bool
    {
        try {
            Mail::send($view, $data, function (Message $message) use ($to, $subject, $callback) {
                $message->to($to)->subject($subject);

                if ($callback) {
                    $callback($message);
                }
            });

            Log::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Send email using a mailable class with exception handling
     *
     * @param \Illuminate\Mail\Mailable $mailable
     * @return bool
     */
    public static function sendMailable($mailable): bool
    {
        try {
            Mail::send($mailable);

            Log::info('Mailable sent successfully', [
                'mailable' => get_class($mailable)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send mailable', [
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Queue email with exception handling
     *
     * @param string|array $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @param callable|null $callback
     * @return bool
     */
    public static function queue($to, string $subject, string $view, array $data = [], callable $callback = null): bool
    {
        try {
            Mail::queue($view, $data, function (Message $message) use ($to, $subject, $callback) {
                $message->to($to)->subject($subject);

                if ($callback) {
                    $callback($message);
                }
            });

            Log::info('Email queued successfully', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue email', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Queue mailable with exception handling
     *
     * @param \Illuminate\Mail\Mailable $mailable
     * @return bool
     */
    public static function queueMailable($mailable): bool
    {
        try {
            Mail::queue($mailable);

            Log::info('Mailable queued successfully', [
                'mailable' => get_class($mailable)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue mailable', [
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Check if mail configuration is working
     *
     * @return bool
     */
    public static function isMailConfigured(): bool
    {
        try {
            $config = config('mail.default');
            $driver = config("mail.mailers.{$config}.transport");

            // Check if the driver is configured
            if (!$driver) {
                Log::warning('Mail driver not configured', ['default' => $config]);
                return false;
            }

            // Check if it's a known driver
            $knownDrivers = ['smtp', 'sendmail', 'mail', 'log', 'array'];
            if (!in_array($driver, $knownDrivers)) {
                Log::warning('Unknown mail driver', ['driver' => $driver]);
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to check mail configuration', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
