<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrganizationCollection;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-organizations')->only(['index', 'show']);
        $this->middleware('permission:create-organizations')->only(['create', 'store']);
        $this->middleware('permission:edit-organizations')->only(['edit', 'update']);
        $this->middleware('permission:delete-organizations')->only('destroy');
    }

    public function index(): Response
    {
        return Inertia::render('Organizations/Index', [
            'filters' => Request::only(['search', 'trashed']),
            'organizations' => new OrganizationCollection(
                Auth::user()->account->organizations()
                    ->orderBy('name')
                    ->filter(Request::only(['search', 'trashed']))
                    ->paginate()
                    ->appends(Request::all())
            ),
        ]);
    }
}
