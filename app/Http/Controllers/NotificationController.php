<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Display user notifications
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Simulated notifications - in production, these would come from a notifications table
        $notifications = collect([
            [
                'id' => '1',
                'type' => 'order_update',
                'title' => 'Order Approved',
                'message' => 'Your order #12345 has been approved and sent to manufacturer',
                'read' => false,
                'created_at' => Carbon::now()->subHours(2)->toIso8601String(),
                'action_url' => '/orders/12345',
                'icon' => 'check-circle',
                'priority' => 'high',
            ],
            [
                'id' => '2',
                'type' => 'ivr_reminder',
                'title' => 'IVR Expiring Soon',
                'message' => 'IVR for patient AB1234 expires in 3 days',
                'read' => false,
                'created_at' => Carbon::now()->subDays(1)->toIso8601String(),
                'action_url' => '/provider/episodes/123',
                'icon' => 'alert-triangle',
                'priority' => 'medium',
            ],
            [
                'id' => '3',
                'type' => 'system',
                'title' => 'New Feature Available',
                'message' => 'Voice commands are now available in the dashboard',
                'read' => true,
                'created_at' => Carbon::now()->subDays(3)->toIso8601String(),
                'action_url' => null,
                'icon' => 'info',
                'priority' => 'low',
            ],
        ]);

        // Filter by read status if requested
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $notifications = $notifications->filter(fn($n) => !$n['read']);
            } elseif ($request->filter === 'read') {
                $notifications = $notifications->filter(fn($n) => $n['read']);
            }
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $notifications->filter(fn($n) => !$n['read'])->count(),
            'filter' => $request->filter ?? 'all',
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId)
    {
        // In production, this would update the notification in the database
        
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        // In production, this would update all notifications for the user
        
        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy($notificationId)
    {
        // In production, this would delete the notification from the database
        
        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Get notification preferences
     */
    public function preferences()
    {
        $user = Auth::user();
        
        // Simulated preferences - in production, these would come from user settings
        $preferences = [
            'email_notifications' => [
                'order_updates' => true,
                'ivr_reminders' => true,
                'system_updates' => false,
            ],
            'push_notifications' => [
                'order_updates' => true,
                'ivr_reminders' => true,
                'system_updates' => false,
            ],
            'notification_frequency' => 'immediate', // immediate, daily, weekly
            'quiet_hours' => [
                'enabled' => true,
                'start' => '22:00',
                'end' => '08:00',
            ],
        ];

        return Inertia::render('Notifications/Preferences', [
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'email_notifications' => 'required|array',
            'push_notifications' => 'required|array',
            'notification_frequency' => 'required|in:immediate,daily,weekly',
            'quiet_hours' => 'required|array',
        ]);

        // In production, save preferences to database

        return back()->with('success', 'Notification preferences updated');
    }
}