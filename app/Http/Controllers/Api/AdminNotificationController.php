<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminNotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ivr,order',
            'orderId' => 'required|string',
            'status' => 'required|string',
            'comments' => 'nullable|string',
            'recipientEmail' => 'required|email',
            'recipientName' => 'required|string',
        ]);

        try {
            $type = $request->input('type');
            $orderId = $request->input('orderId');
            $status = $request->input('status');
            $comments = $request->input('comments');
            $recipientEmail = $request->input('recipientEmail');
            $recipientName = $request->input('recipientName');

            // Determine email template and subject based on type and status
            $emailData = $this->prepareEmailData($type, $orderId, $status, $comments, $recipientName);

            // Send email via Mailtrap
            Mail::to($recipientEmail)->send(new AdminNotificationMail($emailData));

            // Log the notification
            Log::info('Admin notification sent', [
                'type' => $type,
                'orderId' => $orderId,
                'status' => $status,
                'recipient' => $recipientEmail,
                'admin' => auth()->user()->name ?? 'System',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification'
            ], 500);
        }
    }

    private function prepareEmailData($type, $orderId, $status, $comments, $recipientName)
    {
        $baseUrl = config('app.url');
        $orderDetailsUrl = "{$baseUrl}/admin/orders/{$orderId}";

        if ($type === 'ivr') {
            return [
                'subject' => "Order Update – {$orderId} IVR Status: {$status}",
                'recipientName' => $recipientName,
                'orderId' => $orderId,
                'updateType' => 'IVR',
                'newStatus' => $status,
                'comments' => $comments,
                'orderDetailsUrl' => $orderDetailsUrl,
                'template' => 'ivr-notification',
            ];
        } else {
            return [
                'subject' => "Order Update – {$orderId} Status: {$status}",
                'recipientName' => $recipientName,
                'orderId' => $orderId,
                'updateType' => 'Order',
                'newStatus' => $status,
                'comments' => $comments,
                'orderDetailsUrl' => $orderDetailsUrl,
                'template' => 'order-notification',
            ];
        }
    }
}
