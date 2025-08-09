<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunWebhookController extends Controller
{
    /**
     * Handle Mailgun event webhooks.
     * Verifies the signature and logs basic event details.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (!$this->verifySignature($payload['signature'] ?? [])) {
            Log::warning('Mailgun webhook signature verification failed');
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $event = $payload['event-data']['event'] ?? null;
        $recipient = $payload['event-data']['recipient'] ?? null;
        $messageId = $payload['event-data']['message']['headers']['message-id'] ?? null;
        $severity = $payload['event-data']['severity'] ?? null;

        Log::info('Mailgun webhook received', [
            'event' => $event,
            'recipient' => $recipient,
            'message_id' => $messageId,
            'severity' => $severity,
        ]);

        // Attempt to correlate back to a notification
        if ($messageId) {
            $notification = OrderEmailNotification::where('message_id', $messageId)->first();
            if (!$notification) {
                // Try to match by custom variables we set
                $vars = $payload['event-data']['user-variables'] ?? [];
                if (isset($vars['notification_id'])) {
                    $notification = OrderEmailNotification::find($vars['notification_id']);
                }
            }
            if ($notification) {
                if ($event === 'delivered') {
                    $notification->markAsDelivered();
                } elseif ($event === 'failed') {
                    $notification->markAsFailed($payload['event-data']['delivery-status']['description'] ?? 'Mailgun delivery failed');
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function verifySignature(array $signature): bool
    {
        $timestamp = $signature['timestamp'] ?? null;
        $token = $signature['token'] ?? null;
        $sig = $signature['signature'] ?? null;

        if (!$timestamp || !$token || !$sig) {
            return false;
        }

        // Prevent replay attacks: reject if timestamp is too old (>15 minutes)
        if (abs(time() - (int) $timestamp) > 900) {
            return false;
        }

        $signingKey = config('services.mailgun.webhook_signing_secret') ?? env('MAILGUN_WEBHOOK_SIGNING_SECRET');
        if (!$signingKey) {
            // If no signing key configured, fail closed
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.$token, $signingKey);

        // Timing-safe compare
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $sig);
        }

        return $expected === $sig;
    }
}
