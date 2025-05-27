<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-orders')->only(['index', 'show']);
        $this->middleware('permission:create-orders')->only(['create', 'store']);
        $this->middleware('permission:edit-orders')->only(['edit', 'update']);
        $this->middleware('permission:delete-orders')->only('destroy');
        $this->middleware('permission:approve-orders')->only('approval');
    }

    public function index(): Response
    {

        return Inertia::render('Order/Index');
    }
     public function create(): Response
    {

        return Inertia::render('Order/CreateOrder');
    }

    public function approval(): Response

    {

        return Inertia::render('Order/OrderApproval');
    }
}
