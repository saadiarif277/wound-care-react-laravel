<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
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
