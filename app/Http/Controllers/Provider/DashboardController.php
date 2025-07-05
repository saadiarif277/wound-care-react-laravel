<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Order\ProductRequest;
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

        // Get provider's product requests
        $productRequests = ProductRequest::where('provider_id', $user->id)
            ->with(['provider', 'facility', 'products.manufacturer'])
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Transform product requests for frontend
        $transformedProductRequests = $productRequests->map(function ($productRequest) {
            return [
                'id' => $productRequest->id,
                'order_number' => $productRequest->request_number,
                'patient_display_id' => $productRequest->patient_display_id,
                'status' => $productRequest->order_status,
                'created_at' => $productRequest->created_at->toIso8601String(),
                'expected_service_date' => $productRequest->expected_service_date,
                'products' => $productRequest->products->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'quantity' => $p->pivot->quantity ?? 1,
                ]),
                'manufacturer' => [
                    'name' => $this->getManufacturerName($productRequest),
                ],
                'ivr_status' => $productRequest->ivr_status ?? 'pending',
                'tracking_number' => $productRequest->tracking_number,
                'action_required' => $this->checkActionRequired($productRequest),
                'priority' => $this->calculatePriority($productRequest),
            ];
        });

        // Calculate stats based on ProductRequest statuses
        $stats = [
            'total_orders' => $productRequests->count(),
            'pending_ivr' => $productRequests->where('order_status', 'pending')->count(),
            'in_progress' => $productRequests->whereIn('order_status', ['submitted_to_manufacturer'])->count(),
            'completed' => $productRequests->whereIn('order_status', ['confirmed_by_manufacturer'])->count(),
            'success_rate' => $this->calculateSuccessRate($productRequests),
            'average_completion_time' => $this->calculateAverageCompletionTime($productRequests),
        ];

        // Get recent activity
        $recentActivity = $this->getRecentActivity($user->id);

        // Get upcoming deadlines
        $upcomingDeadlines = $this->getUpcomingDeadlines($user->id);

        // Generate AI insights
        $aiInsights = $this->generateAIInsights($productRequests, $stats, $upcomingDeadlines);

        return Inertia::render('Provider/Orders/Dashboard', [
            'orders' => $transformedProductRequests,
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

    private function checkActionRequired($productRequest)
    {
        return in_array($productRequest->order_status, ['pending', 'rejected']);
    }

    private function calculatePriority($productRequest)
    {
        if ($productRequest->order_status === 'rejected') return 'critical';
        if ($productRequest->order_status === 'pending') return 'high';
        if ($productRequest->order_status === 'submitted_to_manufacturer') return 'medium';
        return 'low';
    }

    private function calculateSuccessRate($productRequests)
    {
        $total = $productRequests->count();
        if ($total === 0) return 0;

        $successful = $productRequests->where('order_status', 'confirmed_by_manufacturer')->count();
        return round(($successful / $total) * 100, 1);
    }

    private function calculateAverageCompletionTime($productRequests)
    {
        $completedProductRequests = $productRequests->where('order_status', 'confirmed_by_manufacturer');
        if ($completedProductRequests->isEmpty()) return 0;

        $totalDays = $completedProductRequests->sum(function ($productRequest) {
            return $productRequest->created_at->diffInDays($productRequest->updated_at);
        });

        return round($totalDays / $completedProductRequests->count(), 1);
    }

    private function getRecentActivity($providerId)
    {
        // Get recent product request status changes and activities
        $recentProductRequests = ProductRequest::where('provider_id', $providerId)
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();

        $activities = collect();

        foreach ($recentProductRequests as $productRequest) {
            // Add activity based on product request status
            $activities->push([
                'id' => uniqid(),
                'type' => $this->getActivityType($productRequest),
                'description' => $this->getActivityDescription($productRequest),
                'timestamp' => $productRequest->updated_at->toIso8601String(),
            ]);

            // Add IVR completion activity if applicable
            if ($productRequest->ivr_status === 'verified' && $productRequest->updated_at->diffInDays() <= 7) {
                $activities->push([
                    'id' => uniqid(),
                    'type' => 'ivr_completed',
                    'description' => "IVR completed for patient {$productRequest->patient_display_id}",
                    'timestamp' => $productRequest->updated_at->subHours(1)->toIso8601String(),
                ]);
            }

            // Add tracking activity if tracking number exists
            if ($productRequest->tracking_number && $productRequest->updated_at->diffInDays() <= 7) {
                $activities->push([
                    'id' => uniqid(),
                    'type' => 'tracking_added',
                    'description' => "Tracking added for product request #{$productRequest->request_number}",
                    'timestamp' => $productRequest->updated_at->addHours(2)->toIso8601String(),
                ]);
            }
        }

        return $activities->sortByDesc('timestamp')->take(5)->values();
    }

    private function getUpcomingDeadlines($providerId)
    {
        $deadlines = collect();

        // Get product requests that need IVR completion
        $pendingIvrProductRequests = ProductRequest::where('provider_id', $providerId)
            ->where('order_status', 'pending')
            ->get();

        foreach ($pendingIvrProductRequests as $productRequest) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Complete IVR for patient {$productRequest->patient_display_id}",
                'due_date' => Carbon::parse($productRequest->expected_service_date)->subDays(3)->toIso8601String(),
                'priority' => 'high',
            ]);
        }

        // Get product requests that were rejected and need review
        $rejectedProductRequests = ProductRequest::where('provider_id', $providerId)
            ->where('order_status', 'rejected')
            ->get();

        foreach ($rejectedProductRequests as $productRequest) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Review rejected product request #{$productRequest->request_number}",
                'due_date' => Carbon::parse($productRequest->updated_at)->addDays(2)->toIso8601String(),
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

        // Get product requests approaching service date without approval
        $approachingServiceDate = ProductRequest::where('provider_id', $providerId)
            ->whereIn('order_status', ['submitted_to_manufacturer'])
            ->where('expected_service_date', '<=', Carbon::now()->addDays(7))
            ->get();

        foreach ($approachingServiceDate as $productRequest) {
            $deadlines->push([
                'id' => uniqid(),
                'description' => "Product request #{$productRequest->request_number} service date approaching",
                'due_date' => Carbon::parse($productRequest->expected_service_date)->toIso8601String(),
                'priority' => 'medium',
            ]);
        }

        return $deadlines->sortBy('due_date')->take(5)->values();
    }

    private function getActivityType($productRequest)
    {
        $statusMap = [
            'pending' => 'order_created',
            'submitted_to_manufacturer' => 'ivr_sent',
            'confirmed_by_manufacturer' => 'order_approved',
            'rejected' => 'order_denied',
        ];

        return $statusMap[$productRequest->order_status] ?? 'status_changed';
    }

    private function getActivityDescription($productRequest)
    {
        $descriptions = [
            'pending' => "Product request #{$productRequest->request_number} created, IVR needed",
            'submitted_to_manufacturer' => "IVR sent for product request #{$productRequest->request_number}",
            'confirmed_by_manufacturer' => "Product request #{$productRequest->request_number} approved",
            'rejected' => "Product request #{$productRequest->request_number} rejected - action required",
        ];

        return $descriptions[$productRequest->order_status] ?? "Product request #{$productRequest->request_number} status updated";
    }

    private function generateAIInsights($productRequests, $stats, $upcomingDeadlines)
    {
        $insights = [];

        // Count action-required items
        $pendingIvr = $productRequests->where('order_status', 'pending')->count();
        $rejected = $productRequests->where('order_status', 'rejected')->count();

        // Get expired IVR count from episodes
        $expiredIvr = PatientManufacturerIVREpisode::whereHas('orders', function ($query) use ($productRequests) {
                $query->whereIn('id', $productRequests->pluck('id'));
            })
            ->where('ivr_status', 'expired')
            ->count();

        $expiringDeadlines = $upcomingDeadlines->where('due_date', '<=', Carbon::now()->addDays(3)->toIso8601String())->count();

        // Generate contextual messages
        if ($pendingIvr > 0) {
            $insights[] = "{$pendingIvr} product request" . ($pendingIvr > 1 ? 's' : '') . " need IVR completion";
        }

        if ($rejected > 0) {
            $insights[] = "{$rejected} product request" . ($rejected > 1 ? 's' : '') . " rejected - action required";
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
                $insights[] = "All product requests on track! " . $stats['success_rate'] . "% success rate";
            } else {
                $insights[] = "Ready to create new product requests";
            }
        }

        return [
            'message' => implode(' • ', $insights),
            'urgentCount' => $pendingIvr + $rejected + $expiredIvr,
            'upcomingCount' => $expiringDeadlines,
            'hasActions' => !empty($insights) && ($pendingIvr > 0 || $rejected > 0 || $expiredIvr > 0),
        ];
    }

    /**
     * Get manufacturer name for a product request
     */
    private function getManufacturerName($productRequest)
    {
        // Try to get manufacturer from products (ensure relationship is loaded)
        if ($productRequest->relationLoaded('products')) {
            $firstProduct = $productRequest->products->first();
            if ($firstProduct) {
                // Check if manufacturer is loaded and is an object
                if ($firstProduct->relationLoaded('manufacturer') && $firstProduct->manufacturer && is_object($firstProduct->manufacturer)) {
                    return $firstProduct->manufacturer->name;
                }

                // If manufacturer_id exists, try to get the manufacturer
                if ($firstProduct->manufacturer_id) {
                    $manufacturer = \App\Models\Order\Manufacturer::find($firstProduct->manufacturer_id);
                    return $manufacturer ? $manufacturer->name : 'Unknown';
                }
            }
        }

        // Try to get from clinical summary
        $clinicalSummary = $productRequest->clinical_summary;
        if (is_array($clinicalSummary) && isset($clinicalSummary['product_selection']['manufacturer_id'])) {
            $manufacturer = \App\Models\Order\Manufacturer::find($clinicalSummary['product_selection']['manufacturer_id']);
            return $manufacturer ? $manufacturer->name : 'Unknown';
        }

        // Try to get from clinical summary if it's a JSON string
        if (is_string($clinicalSummary)) {
            $decoded = json_decode($clinicalSummary, true);
            if (is_array($decoded) && isset($decoded['product_selection']['manufacturer_id'])) {
                $manufacturer = \App\Models\Order\Manufacturer::find($decoded['product_selection']['manufacturer_id']);
                return $manufacturer ? $manufacturer->name : 'Unknown';
            }
        }

        return 'Unknown';
    }
}
