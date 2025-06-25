<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class EligibilityPageController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:view-eligibility']);
    }

    /**
     * Display the Eligibility SPA screen (front-end calls API endpoints)
     */
    public function index(): Response
    {
        return Inertia::render('Eligibility/Index');
    }
}
