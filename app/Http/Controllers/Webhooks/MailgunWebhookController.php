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
     * Verifies the signature and processes events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid Mailgun webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event-data.event');
        $data = $request->input('event-data');

        Log::info('Mailgun webhook received', [
            'event' => $event,
            'recipient' => $data['recipient'] ?? null,
            'message_id' => $data['message']['headers']['message-id'] ?? null,
        ]);

        switch ($event) {
            case 'delivered':
                $this->handleDelivered($data);
                break;
            case 'opened':
                $this->handleOpened($data);
                break;
            case 'clicked':
                $this->handleClicked($data);
                break;
            case 'failed':
                $this->handleFailed($data);
                break;
            case 'complained':
                $this->handleComplained($data);
                break;
            default:
                Log::info('Unhandled Mailgun webhook event', ['event' => $event]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verify Mailgun webhook signature
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $timestamp = $request->input('signature.timestamp');
        $token = $request->input('signature.token');
        $signature = $request->input('signature.signature');
        $signingKey = config('services.mailgun.webhook_signing_secret');

        if (!$timestamp || !$token || !$signature || !$signingKey) {
            return false;
        }

        // Check timestamp (within 10 minutes)
        if (abs(time() - $timestamp) > 600) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . $token, $signingKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle delivered event
     */
    private function handleDelivered(array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        $messageId = $data['message']['headers']['message-id'] ?? null;

        Log::info('Email delivered successfully', [
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);

        // Update email log status if you have email logging
        $this->updateEmailLog($messageId, 'delivered', $data);
    }

    /**
     * Handle opened event
     */
    private function handleOpened(array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        $messageId = $data['message']['headers']['message-id'] ?? null;

        Log::info('Email opened', [
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);

        $this->updateEmailLog($messageId, 'opened', $data);
    }

    /**
     * Handle clicked event
     */
    private function handleClicked(array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        $messageId = $data['message']['headers']['message-id'] ?? null;
        $url = $data['url'] ?? null;

        Log::info('Email link clicked', [
            'recipient' => $recipient,
            'message_id' => $messageId,
            'url' => $url,
        ]);

        $this->updateEmailLog($messageId, 'clicked', $data);
    }

    /**
     * Handle failed event
     */
    private function handleFailed(array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        $messageId = $data['message']['headers']['message-id'] ?? null;
        $reason = $data['delivery-status']['description'] ?? 'Unknown';

        Log::error('Email delivery failed', [
            'recipient' => $recipient,
            'message_id' => $messageId,
            'reason' => $reason,
        ]);

        $this->updateEmailLog($messageId, 'failed', $data);

        // Could trigger retry logic or notifications here
    }

    /**
     * Handle complained event (spam report)
     */
    private function handleComplained(array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        $messageId = $data['message']['headers']['message-id'] ?? null;

        Log::warning('Email marked as spam', [
            'recipient' => $recipient,
            'message_id' => $messageId,
        ]);

        $this->updateEmailLog($messageId, 'complained', $data);

        // Could trigger unsubscribe or reputation monitoring
    }

    /**
     * Update email log (if you implement email logging)
     */
    private function updateEmailLog(?string $messageId, string $status, array $data): void
    {
        // Find notification by message ID
        if ($messageId) {
            $notification = OrderEmailNotification::where('message_id', $messageId)->first();
            if ($notification) {
                switch ($status) {
                    case 'delivered':
                        $notification->markAsDelivered();
                        break;
                    case 'opened':
                        $notification->markAsOpened();
                        break;
                    case 'clicked':
                        $notification->markAsClicked();
                        break;
                    case 'failed':
                        $notification->markAsFailed($data['delivery-status']['description'] ?? 'Delivery failed');
                        break;
                    case 'complained':
                        $notification->markAsComplained();
                        break;
                }
            }
        }
    }
}
