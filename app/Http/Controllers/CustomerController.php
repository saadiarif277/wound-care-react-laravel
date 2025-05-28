<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Customers/Index', [
            'customers' => []
        ]);
    }

    public function show($customer)
    {
        return Inertia::render('Customers/Show', [
            'customer' => null
        ]);
    }
} 