<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MacContractorNewsService;

class ResourcesController extends Controller
{
    protected MacContractorNewsService $newsService;

    public function __construct(MacContractorNewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    /**
     * Display the resources page with billing and coding resources
     */
    public function index()
    {
        // Get MAC contractor news summary
        $macNews = [];
        try {
            $macNews = $this->newsService->getNewsSummary();
        } catch (\Exception $e) {
            // Log error but don't fail the page
            Log::warning('Failed to fetch MAC contractor news: ' . $e->getMessage());
        }

        return Inertia::render('Resources/Index', [
            'title' => 'Resources - Billing & Coding',
            'meta' => [
                'description' => 'Comprehensive billing and coding resources including MAC contractor information, fee schedules, and documentation guidelines.',
                'keywords' => 'billing, coding, MAC contractor, Medicare, HCPCS, CPT, wound care, reimbursement'
            ],
            'macNews' => $macNews
        ]);
    }

    /**
     * API endpoint to get MAC contractor news
     */
    public function getMacNews()
    {
        try {
            $news = $this->newsService->getAllNews();
            return response()->json([
                'success' => true,
                'data' => $news
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch MAC contractor news: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch news',
                'message' => 'Unable to retrieve the latest news from MAC contractors'
            ], 500);
        }
    }

    /**
     * API endpoint to get news from a specific MAC contractor
     */
    public function getContractorNews(string $contractor)
    {
        try {
            $news = $this->newsService->getContractorNews($contractor);
            return response()->json([
                'success' => true,
                'data' => $news
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to fetch news for contractor {$contractor}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch contractor news',
                'message' => "Unable to retrieve news for {$contractor}"
            ], 500);
        }
    }
} 