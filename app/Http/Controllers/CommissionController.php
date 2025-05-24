<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CommissionController extends Controller
{
    public function index()
    {
        return Inertia::render('Commission/Dashboard');
    }
}
