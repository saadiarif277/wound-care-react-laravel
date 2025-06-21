<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:manage-system-config']);
    }

    /**
     * Show system configuration page
     */
    public function config()
    {
        return Inertia::render('SystemAdmin/Config');
    }

    /**
     * Show integrations management page
     */
    public function integrations()
    {
        return Inertia::render('SystemAdmin/Integrations');
    }

    /**
     * Show API management page
     */
    public function api()
    {
        return Inertia::render('SystemAdmin/Api');
    }

    /**
     * Show audit logs page
     */
    public function audit()
    {
        return Inertia::render('SystemAdmin/Audit');
    }
}
