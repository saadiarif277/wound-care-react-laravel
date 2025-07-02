<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNotificationMail;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class OrderStatusController extends Controller
{
    public function updateStatus(Request $request)
    {
        $request->validate([
            'orderId' => 'required|string',
            'type' => 'required|in:ivr,order',
            'status' => 'required|string',
            'comments' => 'nullable|string',
            'rejectionReason' => 'nullable|string',
            'cancellationReason' => 'nullable|string',
            'sendNotification' => 'required|boolean',
            'carrier' => 'nullable|string',
            'trackingNumber' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::where('id', $request->orderId)->firstOrFail();
            $type = $request->input('type');
            $status = $request->input('status');
            $comments = $request->input('comments');
            $sendNotification = $request->input('sendNotification');

            // Update order status based on type
            if ($type === 'ivr') {
                $order->ivr_status = $status;
                if ($status === 'Sent') {
                    $order->ivr_sent_date = now();
                } elseif ($status === 'Verified') {
                    $order->ivr_verified_date = now();
                }
            } else {
                $order->order_status = $status;
                if ($status === 'Submitted to Manufacturer') {
                    $order->order_submission_date = now();
                } elseif ($status === 'Confirmed by Manufacturer') {
                    $order->order_approval_date = now();
                    $order->carrier = $request->input('carrier');
                    $order->tracking_number = $request->input('trackingNumber');
                }
            }

            // Handle rejection/cancellation reasons
            if ($status === 'Rejected' && $request->input('rejectionReason')) {
                if ($type === 'ivr') {
                    $order->ivr_rejection_reason = $request->input('rejectionReason');
                } else {
                    $order->order_rejection_reason = $request->input('rejectionReason');
                }
            }

            if ($status === 'Canceled' && $request->input('cancellationReason')) {
                $order->order_cancellation_reason = $request->input('cancellationReason');
            }

            // Add comments
            if ($comments) {
                if ($type === 'ivr') {
                    $order->ivr_notes = $comments;
                } else {
                    $order->order_notes = $comments;
                }
            }

            $order->save();

            // Handle file uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $this->uploadDocument($order, $file, $type);
                }
            }

            // Create audit log
            $this->createAuditLog($order, $type, $status, $comments, $request->all());

            // Send notification if requested
            if ($sendNotification) {
                $this->sendNotification($order, $type, $status, $comments);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ucfirst($type) . ' status updated successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update order status', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    private function uploadDocument($order, $file, $type)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs("orders/{$order->id}/{$type}", $fileName, 'public');

        Document::create([
            'documentable_type' => Order::class,
            'documentable_id' => $order->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'type' => $type . '_document',
            'uploaded_by' => auth()->id(),
            'status' => 'active',
        ]);
    }

    private function createAuditLog($order, $type, $status, $comments, $requestData)
    {
        $action = "Updated {$type} status to {$status}";
        $details = [
            'previous_status' => $type === 'ivr' ? $order->getOriginal('ivr_status') : $order->getOriginal('order_status'),
            'new_status' => $status,
            'comments' => $comments,
            'rejection_reason' => $requestData['rejectionReason'] ?? null,
            'cancellation_reason' => $requestData['cancellationReason'] ?? null,
            'carrier' => $requestData['carrier'] ?? null,
            'tracking_number' => $requestData['trackingNumber'] ?? null,
            'notification_sent' => $requestData['sendNotification'] ?? false,
            'files_uploaded' => $requestData['files'] ? count($requestData['files']) : 0,
        ];

        OrderAuditLog::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function sendNotification($order, $type, $status, $comments)
    {
        try {
            // Get recipient information from order
            $recipientEmail = $order->provider_email ?? $order->organization_email;
            $recipientName = $order->provider_name ?? $order->organization_name;

            if (!$recipientEmail) {
                Log::warning('No recipient email found for notification', ['order_id' => $order->id]);
                return;
            }

            $emailData = [
                'subject' => "Order Update – {$order->id} " . strtoupper($type) . " Status: {$status}",
                'recipientName' => $recipientName,
                'orderId' => $order->id,
                'updateType' => strtoupper($type),
                'newStatus' => $status,
                'comments' => $comments,
                'orderDetailsUrl' => config('app.url') . "/admin/orders/{$order->id}",
                'template' => $type . '-notification',
            ];

            Mail::to($recipientEmail)->send(new AdminNotificationMail($emailData));

            Log::info('Status update notification sent', [
                'order_id' => $order->id,
                'type' => $type,
                'status' => $status,
                'recipient' => $recipientEmail,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send status update notification', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
        }
    }

    public function removeDocument(Request $request)
    {
        $request->validate([
            'documentId' => 'required|string',
        ]);

        try {
            $document = Document::findOrFail($request->documentId);

            // Check permissions
            if (!auth()->user()->can('delete', $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this document'
                ], 403);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Create audit log
            OrderAuditLog::create([
                'order_id' => $document->order_id,
                'user_id' => auth()->id(),
                'action' => "Removed document: {$document->name}",
                'details' => json_encode([
                    'document_id' => $document->id,
                    'document_name' => $document->name,
                    'document_type' => $document->document_type,
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove document', [
                'error' => $e->getMessage(),
                'document_id' => $request->documentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove document'
            ], 500);
        }
    }
}
