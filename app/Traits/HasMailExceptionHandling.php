<?php

namespace App\Traits;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

trait HasMailExceptionHandling
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
    protected function sendEmailSafely($to, string $subject, string $view, array $data = [], callable $callback = null): bool
    {
        try {
            Mail::send($view, $data, function ($message) use ($to, $subject, $callback) {
                $message->to($to)->subject($subject);
                
                if ($callback) {
                    $callback($message);
                }
            });

            Log::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'model' => get_class($this)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'model' => get_class($this),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Send mailable with exception handling
     *
     * @param \Illuminate\Mail\Mailable $mailable
     * @return bool
     */
    protected function sendMailableSafely($mailable): bool
    {
        try {
            Mail::send($mailable);

            Log::info('Mailable sent successfully', [
                'mailable' => get_class($mailable),
                'model' => get_class($this)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send mailable', [
                'mailable' => get_class($mailable),
                'model' => get_class($this),
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
    protected function queueEmailSafely($to, string $subject, string $view, array $data = [], callable $callback = null): bool
    {
        try {
            Mail::queue($view, $data, function ($message) use ($to, $subject, $callback) {
                $message->to($to)->subject($subject);
                
                if ($callback) {
                    $callback($message);
                }
            });

            Log::info('Email queued successfully', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'model' => get_class($this)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue email', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'model' => get_class($this),
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
    protected function queueMailableSafely($mailable): bool
    {
        try {
            Mail::queue($mailable);

            Log::info('Mailable queued successfully', [
                'mailable' => get_class($mailable),
                'model' => get_class($this)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue mailable', [
                'mailable' => get_class($mailable),
                'model' => get_class($this),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return false;
        }
    }

    /**
     * Check if mail is configured and working
     *
     * @return bool
     */
    protected function isMailWorking(): bool
    {
        try {
            $config = config('mail.default');
            $driver = config("mail.mailers.{$config}.transport");
            
            if (!$driver) {
                return false;
            }

            // Check if it's a known driver
            $knownDrivers = ['smtp', 'sendmail', 'mail', 'log', 'array'];
            return in_array($driver, $knownDrivers);
        } catch (Exception $e) {
            Log::error('Failed to check mail configuration', [
                'error' => $e->getMessage(),
                'model' => get_class($this)
            ]);
            return false;
        }
    }
} 