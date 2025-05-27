<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-reports')->only(['index', 'show']);
        $this->middleware('permission:create-reports')->only(['create', 'store']);
        $this->middleware('permission:edit-reports')->only(['edit', 'update']);
        $this->middleware('permission:delete-reports')->only('destroy');
        $this->middleware('permission:export-reports')->only('export');
    }

    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }
}
