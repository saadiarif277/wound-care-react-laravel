<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /**
     * Redirect legacy order routes to consolidated order management
     */
    public function orderRedirect()
    {
        return redirect()->route('orders.management');
    }

    /**
     * Redirect legacy organization routes to consolidated organizations & analytics
     */
    public function organizationRedirect()
    {
        return redirect()->route('admin.organizations.index');
    }

    /**
     * Redirect legacy commission routes to consolidated sales management
     */
    public function commissionRedirect()
    {
        return redirect()->route('commission.management');
    }

    /**
     * Redirect legacy DocuSeal routes to consolidated order management
     */
    public function docusealRedirect()
    {
        return redirect()->route('orders.management');
    }
}
