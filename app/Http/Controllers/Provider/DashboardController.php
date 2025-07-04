<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Enhanced provider dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get provider's orders
        $orders = Order::where('provider_id', $user->id)
            ->with(['patient', 'manufacturer', 'products', 'ivrEpisode'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Transform orders for frontend
        $transformedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'patient_display_id' => $order->patient_display_id,
                'status' => $order->order_status,
                'created_at' => $order->created_at->toIso8601String(),
                'expected_service_date' => $order->date_of_service,
                'products' => $order->products->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'quantity' => $p->pivot->quantity ?? 1,
                ]),
                'manufacturer' => [
                    'name' => $order->manufacturer->name ?? 'Unknown',
                ],
                'ivr_status' => $order->ivrEpisode->ivr_status ?? 'pending',
                'tracking_number' => $order->tracking_number,
                'action_required' => $this->checkActionRequired($order),
                'priority' => $this->calculatePriority($order),
            ];
        });

        // Calculate stats
        $stats = [
            'total_orders' => $orders->count(),
            'pending_ivr' => $orders->where('order_status', 'pending_ivr')->count(),
            'in_progress' => $orders->whereIn('order_status', ['ivr_sent', 'approved', 'submitted_to_manufacturer'])->count(),
            'completed' => $orders->where('order_status', 'delivered')->count(),
            'success_rate' => $this->calculateSuccessRate($orders),
            'average_completion_time' => $this->calculateAverageCompletionTime($orders),
        ];

        // Get recent activity
        $recentActivity = $this->getRecentActivity($user->id);

        // Get upcoming deadlines
        $upcomingDeadlines = $this->getUpcomingDeadlines($user->id);

        // Generate AI insights
        $aiInsights = $this->generateAIInsights($orders, $stats, $upcomingDeadlines);

        return Inertia::render('Provider/Orders/Dashboard', [
            'orders' => $transformedOrders,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'upcomingDeadlines' => $upcomingDeadlines,
            'aiInsights' => $aiInsights,
        ]);
    }

    /**
     * Provider's episodes
     */
    public function episodes(Request $request)
    {
        $user = Auth::user();

        // Get episodes related to provider's orders
        $episodes = PatientManufacturerIVREpisode::whereHas('orders', function ($query) use ($user) {
                $query->where('provider_id', $user->id);
            })
            ->with(['manufacturer', 'orders' => function ($query) use ($user) {
                $query->where('provider_id', $user->id)
                    ->with(['products']);
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform the paginated data
        $episodes->through(function ($episode) {
            return [
                'id' => $episode->id,
                'patient_id' => $episode->patient_id,
                'patient_name' => $episode->patient_name,
                'patient_display_id' => $episode->patient_display_id,
                'status' => $episode->status,
                'ivr_status' => $episode->ivr_status,
                'verification_date' => $episode->verification_date?->toISOString(),
                'expiration_date' => $episode->expiration_date?->toISOString(),
                'manufacturer' => [
                    'id' => $episode->manufacturer->id ?? null,
                    'name' => $episode->manufacturer->name ?? 'Unknown',
                ],
                'orders' => $episode->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_status' => $order->order_status,
                        'created_at' => $order->created_at->toISOString(),
                        'products' => $order->products->map(function ($product) {
                            return [
                                'id' => $product->id,
                                'name' => $product->name,
                                'quantity' => $product->pivot->quantity ?? 1,
                            ];
                        }),
                    ];
                }),
                'created_at' => $episode->created_at->toISOString(),
                'updated_at' => $episode->updated_at->toISOString(),
            ];
        });

        return Inertia::render('Provider/Episodes/Index', [
            'episodes' => $episodes,
        ]);
    }

    /**
     * Show specific episode
     */
    public function showEpisode($episodeId)
    {
        $user = Auth::user();

        // First check if episode exists at all
        $episodeExists = PatientManufacturerIVREpisode::where('id', $episodeId)->exists();
        
        if (!$episodeExists) {
            Log::error('Episode not found', ['episode_id' => $episodeId]);
            abort(404, 'Episode not found');
        }
        
        // Debug: Check what orders exist for this episode
        $orderCount = \App\Models\Order\Order::where('episode_id', $episodeId)->count();
        $providerOrderCount = \App\Models\Order\Order::where('episode_id', $episodeId)
            ->where('provider_id', $user->id)
            ->count();
            
        Log::info('Episode access check', [
            'episode_id' => $episodeId,
            'user_id' => $user->id,
            'total_orders' => $orderCount,
            'provider_orders' => $providerOrderCount,
            'user_roles' => $user->roles->pluck('slug')->toArray()
        ]);

        // Ensure provider has access to this episode through orders
        $episode = PatientManufacturerIVREpisode::where('id', $episodeId)
            ->whereHas('orders', function ($query) use ($user) {
                $query->where('provider_id', $user->id);
            })
            ->with(['manufacturer', 'orders' => function ($query) use ($user) {
                $query->where('provider_id', $user->id)
                    ->with(['products', 'facility', 'provider']);
            }])
            ->first();
            
        if (!$episode) {
            Log::error('Provider does not have access to episode', [
                'episode_id' => $episodeId,
                'provider_id' => $user->id
            ]);
            abort(403, 'You do not have access to this episode');
        }

        // Check permissions
        $can_view_episode = $user->hasPermission('view-orders');
        $can_view_tracking = $user->hasPermission('view-order-tracking');
        $can_view_documents = $user->hasPermission('view-documents');

        // Transform episode data
        $transformedEpisode = [
            'id' => $episode->id,
            'patient_id' => $episode->patient_id,
            'patient_name' => $episode->patient_name,
            'patient_display_id' => $episode->patient_display_id,
            'status' => $episode->status,
            'ivr_status' => $episode->ivr_status,
            'verification_date' => $episode->verification_date?->toISOString(),
            'expiration_date' => $episode->expiration_date?->toISOString(),
            'manufacturer' => [
                'id' => $episode->manufacturer->id ?? null,
                'name' => $episode->manufacturer->name ?? 'Unknown',
                'contact_email' => $episode->manufacturer->contact_email ?? null,
                'contact_phone' => $episode->manufacturer->contact_phone ?? null,
            ],
            'orders' => $episode->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_status' => $order->order_status,
                    'provider' => [
                        'id' => $order->provider->id ?? null,
                        'name' => $order->provider->first_name . ' ' . $order->provider->last_name,
                        'email' => $order->provider->email ?? null,
                        'npi_number' => $order->provider->npi_number ?? null,
                    ],
                    'facility' => [
                        'id' => $order->facility->id ?? null,
                        'name' => $order->facility->name ?? 'Unknown',
                        'city' => $order->facility->city ?? null,
                        'state' => $order->facility->state ?? null,
                    ],
                    'expected_service_date' => $order->date_of_service,
                    'submitted_at' => $order->created_at->toISOString(),
                    'total_order_value' => $order->total_order_value ?? 0,
                    'action_required' => $this->checkActionRequired($order),
                    'products' => $order->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'quantity' => $product->pivot->quantity ?? 1,
                            'unit_price' => $product->pivot->unit_price ?? 0,
                            'total_price' => ($product->pivot->quantity ?? 1) * ($product->pivot->unit_price ?? 0),
                        ];
                    }),
                ];
            }),
            'docuseal' => [
                'status' => $episode->docuseal_status,
                'signed_documents' => $this->getDocusealDocuments($episode),
                'audit_log_url' => $episode->docuseal_audit_log_url,
                'last_synced_at' => $episode->docuseal_last_synced_at?->toISOString(),
            ],
            'total_order_value' => $episode->orders->sum('total_order_value'),
            'orders_count' => $episode->orders->count(),
            'action_required' => $episode->orders->contains(fn($order) => $this->checkActionRequired($order)),
        ];

        return Inertia::render('Provider/Episodes/Show', [
            'episode' => $transformedEpisode,
            'can_view_episode' => $can_view_episode,
            'can_view_tracking' => $can_view_tracking,
            'can_view_documents' => $can_view_documents,
        ]);
    }

    /**
     * Get Docuseal documents for episode
     */
    private function getDocusealDocuments($episode)
    {
        $documents = [];

        // Add signed document if available
        if ($episode->docuseal_signed_document_url) {
            $documents[] = [
                'id' => 1,
                'name' => 'Signed IVR Document',
                'url' => $episode->docuseal_signed_document_url,
            ];
        }

        // TODO: Add more documents when Document model is available
        // $episode->docusealDocuments()->each(function ($doc) use (&$documents) {
        //     $documents[] = [
        //         'id' => $doc->id,
        //         'name' => $doc->name,
        //         'url' => $doc->url,
        //     ];
        // });

        return $documents;
    }

    /**
     * Get API episode stats
     */
    public function getEpisodeStats()
    {
        $user = Auth::user();

        $stats = [
            'total_episodes' => PatientManufacturerIVREpisode::whereHas('orders', function ($q) use ($user) {
                $q->where('provider_id', $user->id);
            })->count(),
            'pending_ivr' => PatientManufacturerIVREpisode::whereHas('orders', function ($q) use ($user) {
                $q->where('provider_id', $user->id);
            })->where('status', 'ready_for_review')->count(),
            'completed_this_week' => PatientManufacturerIVREpisode::whereHas('orders', function ($q) use ($user) {
                $q->where('provider_id', $user->id);
            })
            ->where('status', 'completed')
            ->where('updated_at', '>=', Carbon::now()->startOfWeek())
            ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get API episode activity
     */
    public function getEpisodeActivity()
    {
        $user = Auth::user();

        // Get recent changes to provider's orders/episodes
        $activity = Order::where('provider_id', $user->id)
            ->with(['episode'])
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => uniqid(),
                    'type' => $this->getActivityType($order),
                    'description' => $this->getActivityDescription($order),
                    'timestamp' => $order->updated_at->toIso8601String(),
                ];
            });

        return response()->json($activity);
    }

    /**
     * Process voice command
     */
    public function processVoiceCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
        ]);

        $command = strtolower($request->command);
        $response = [
            'success' => true,
            'action' => null,
            'message' => null,
        ];

        if (str_contains($command, 'new order')) {
            $response['action'] = 'navigate';
            $response['route'] = route('orders.create');
            $response['message'] = 'Opening new order form';
        } elseif (str_contains($command, 'pending')) {
            $response['action'] = 'filter';
            $response['filter'] = 'pending';
            $response['message'] = 'Showing pending orders';
        } elseif (str_contains($command, 'refresh')) {
            $response['action'] = 'refresh';
            $response['message'] = 'Refreshing data';
        } else {
            $response['success'] = false;
            $response['message'] = 'Command not recognized';
        }

        return response()->json($response);
    }

    private function checkActionRequired($order)
    {
        return in_array($order->order_status, ['pending_ivr', 'sent_back', 'denied']);
    }

    private function calculatePriority($order)
    {
        if ($order->order_status === 'denied') return 'critical';
        if ($order->order_status === 'sent_back') return 'high';
        if ($order->order_status === 'pending_ivr') return 'medium';
        return 'low';
    }

    private function calculateSuccessRate($orders)
    {
        $total = $orders->count();
        if ($total === 0) return 0;

        $successful = $orders->where('order_status', 'delivered')->count();
        return round(($successful / $total) * 100, 1);
    }

    private function calculateAverageCompletionTime($orders)
    {
        $completedOrders = $orders->where('order_status', 'delivered');
        if ($completedOrders->isEmpty()) return 0;

        $totalDays = $completedOrders->sum(function ($order) {
            return $order->created_at->diffInDays($order->updated_at);
        });

        return round($totalDays / $completedOrders->count(), 1);
    }

    private function getRecentActivity($providerId)
    {
        // Get recent order status changes and activities
        $recentOrders = Order::where('provider_id', $providerId)
            ->with('ivrEpisode')
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();

        $activities = collect();

        foreach ($recentOrders as $order) {
            // Add activity based on order status
            $activities->push([
                'id' => uniqid(),
                'type' => $this->getActivityType($order),
                'description' => $this->getActivityDescription($order),
                'timestamp' => $order->updated_at->toIso8601String(),
            ]);

            // Add IVR completion activity if applicable (check episode IVR status)
            if ($order->ivrEpisode && $order->ivrEpisode->ivr_status === 'verified' && $order->updated_at->diffInDays() <= 7) {
                $activities->push([
                    'id' => uniqid(),
                    'type' => 'ivr_completed',
                    'description' => "IVR completed for patient {$order->patient_display_id}",
                    'timestamp' => $order->updated_at->subHours(1)->toIso8601String(),
                ]);
            }

            // Add tracking activity if tracking number exists
            if ($order->tracking_number && $order->updated_at->diffInDays() <= 7) {
                $activities->push([
                    'id' => uniqid(),
                    'type' => 'tracking_added',
                    'description' => "Tracking added for order #{$order->order_number}",
                    'timestamp' => $order->updated_at->addHours(2)->toIso8601String(),
                ]);
            }
        }

        return $activities->sortByDesc('timestamp')->take(5)->values();
    }

    private function getUpcomingDeadlines($providerId)
    {
        $deadlines = collect();

        // Get orders that need IVR completion
        $pendingIvrOrders = Order::where('provider_id', $providerId)
            ->where('order_status', 'pending_ivr')
            ->get();

        foreach ($pendingIvrOrders as $order) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Complete IVR for patient {$order->patient_display_id}",
                'due_date' => Carbon::parse($order->date_of_service)->subDays(3)->toIso8601String(),
                'priority' => 'high',
            ]);
        }

        // Get orders that were sent back and need review
        $sentBackOrders = Order::where('provider_id', $providerId)
            ->where('order_status', 'sent_back')
            ->get();

        foreach ($sentBackOrders as $order) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Review sent back order #{$order->order_number}",
                'due_date' => Carbon::parse($order->updated_at)->addDays(2)->toIso8601String(),
                'priority' => 'critical',
            ]);
        }

        // Get episodes with expired IVRs (IVR status is tracked at episode level)
        $expiredIvrEpisodes = PatientManufacturerIVREpisode::whereHas('orders', function ($query) use ($providerId) {
                $query->where('provider_id', $providerId);
            })
            ->where('ivr_status', 'expired')
            ->with(['orders' => function ($query) use ($providerId) {
                $query->where('provider_id', $providerId)->first();
            }])
            ->get();

        foreach ($expiredIvrEpisodes as $episode) {
            $firstOrder = $episode->orders->first();
            if ($firstOrder) {
                $deadlines->push([
                    'id' => uniqid(),
                    'description' => "Renew expired IVR for patient {$firstOrder->patient_display_id}",
                    'due_date' => Carbon::now()->addDays(1)->toIso8601String(),
                    'priority' => 'high',
                ]);
            }
        }

        // Get orders approaching service date without approval
        $approachingServiceDate = Order::where('provider_id', $providerId)
            ->whereIn('order_status', ['ivr_sent', 'submitted_to_manufacturer'])
            ->where('date_of_service', '<=', Carbon::now()->addDays(7))
            ->get();

        foreach ($approachingServiceDate as $order) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Order #{$order->order_number} service date approaching",
                'due_date' => Carbon::parse($order->date_of_service)->toIso8601String(),
                'priority' => 'medium',
            ]);
        }

        return $deadlines->sortBy('due_date')->take(5)->values();
    }

    private function getActivityType($order)
    {
        $statusMap = [
            'pending_ivr' => 'order_created',
            'ivr_sent' => 'ivr_sent',
            'approved' => 'order_approved',
            'denied' => 'order_denied',
            'delivered' => 'order_delivered',
        ];

        return $statusMap[$order->order_status] ?? 'status_changed';
    }

    private function getActivityDescription($order)
    {
        $descriptions = [
            'pending_ivr' => "Order #{$order->order_number} created, IVR needed",
            'ivr_sent' => "IVR sent for order #{$order->order_number}",
            'approved' => "Order #{$order->order_number} approved",
            'denied' => "Order #{$order->order_number} denied - action required",
            'delivered' => "Order #{$order->order_number} delivered successfully",
        ];

        return $descriptions[$order->order_status] ?? "Order #{$order->order_number} status updated";
    }

    private function generateAIInsights($orders, $stats, $upcomingDeadlines)
    {
        $insights = [];

        // Count action-required items
        $pendingIvr = $orders->where('order_status', 'pending_ivr')->count();
        $sentBack = $orders->where('order_status', 'sent_back')->count();

        // Get expired IVR count from episodes, not orders
        $expiredIvr = PatientManufacturerIVREpisode::whereHas('orders', function ($query) use ($orders) {
                $query->whereIn('id', $orders->pluck('id'));
            })
            ->where('ivr_status', 'expired')
            ->count();

        $expiringDeadlines = $upcomingDeadlines->where('due_date', '<=', Carbon::now()->addDays(3)->toIso8601String())->count();

        // Generate contextual messages
        if ($pendingIvr > 0) {
            $insights[] = "{$pendingIvr} order" . ($pendingIvr > 1 ? 's' : '') . " need IVR completion";
        }

        if ($sentBack > 0) {
            $insights[] = "{$sentBack} order" . ($sentBack > 1 ? 's' : '') . " sent back for review";
        }

        if ($expiredIvr > 0) {
            $insights[] = "{$expiredIvr} IVR" . ($expiredIvr > 1 ? 's' : '') . " expired";
        }

        if ($expiringDeadlines > 0) {
            $insights[] = "{$expiringDeadlines} deadline" . ($expiringDeadlines > 1 ? 's' : '') . " expiring soon";
        }

        // If no urgent items, provide positive feedback
        if (empty($insights)) {
            if ($stats['total_orders'] > 0) {
                $insights[] = "All orders on track! " . $stats['success_rate'] . "% success rate";
            } else {
                $insights[] = "Ready to create new orders";
            }
        }

        return [
            'message' => implode(' • ', $insights),
            'urgentCount' => $pendingIvr + $sentBack + $expiredIvr,
            'upcomingCount' => $expiringDeadlines,
            'hasActions' => !empty($insights) && ($pendingIvr > 0 || $sentBack > 0 || $expiredIvr > 0),
        ];
    }
}
