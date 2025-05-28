<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:view-team']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Team/Index', [
            'team' => []
        ]);
    }

    public function show($member)
    {
        return Inertia::render('Team/Show', [
            'member' => null
        ]);
    }
}
