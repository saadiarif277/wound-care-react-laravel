<?php

namespace App\Http\Controllers;

use App\Services\PayerService;
use Illuminate\Http\Request;

class PayerController extends Controller
{
    protected PayerService $payerService;
    
    public function __construct(PayerService $payerService)
    {
        $this->payerService = $payerService;
    }
    
    /**
     * Search payers by name or ID
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 20);
        
        $payers = $this->payerService->searchPayers($query, $limit);
        
        return response()->json([
            'data' => $payers,
            'count' => $payers->count()
        ]);
    }
}