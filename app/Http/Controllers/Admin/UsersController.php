<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Users\Provider\ProviderInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:manage-users');
    }

    /**
     * Display the consolidated admin user management interface
     */
    public function index(Request $request): Response
    {
        // Build users query with eager loading
        $query = User::with(['roles'])
            ->select('id', 'first_name', 'last_name', 'email', 'is_verified', 'last_activity', 'created_at');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_verified', $request->status === 'active');
        }

        // Paginate results
        $users = $query->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->appends($request->all());

        // Transform users data
        $transformedUsers = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                        'slug' => $role->slug,
                    ];
                }),
                'is_active' => $user->is_verified ?? true,
                'last_login' => $user->last_activity,
                'created_at' => $user->created_at,
            ];
        });

        // Get all roles for filter dropdown
        $roles = Role::select('id', 'name', 'display_name', 'slug')
            ->orderBy('display_name')
            ->get();

        // Calculate stats
        $stats = $this->getUserStats();

        return Inertia::render('Admin/Users/Index', [
            'users' => [
                'data' => $transformedUsers,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ],
            'roles' => $roles,
            'stats' => $stats,
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    /**
     * Show user creation form
     */
    public function create(): Response
    {
        $roles = Role::select('id', 'name', 'display_name', 'slug')
            ->orderBy('display_name')
            ->get();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:25',
            'last_name' => 'required|string|max:25',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'is_verified' => 'boolean',
        ]);

        // Get the current user's account ID
        $accountId = auth()->user()->account_id;

        if (!$accountId) {
            return back()->withErrors(['error' => 'Unable to determine account context. Please contact an administrator.']);
        }

        $user = User::create([
            'account_id' => $accountId,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_verified' => $validated['is_verified'] ?? true,
            'owner' => false, // Default to non-owner
        ]);

        $user->roles()->attach($validated['roles']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show user edit form
     */
    public function edit(User $user): Response
    {
        $roles = Role::select('id', 'name', 'display_name', 'slug')
            ->orderBy('display_name')
            ->get();

        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'email' => $user->email,
                'is_verified' => $user->is_verified ?? true,
                'roles' => $user->roles->pluck('id'),
                'created_at' => $user->created_at,
                'last_login' => $user->last_activity,
            ],
            'roles' => $roles,
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:25',
            'last_name' => 'required|string|max:25',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'is_verified' => 'boolean',
        ]);

        $updateData = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'is_verified' => $validated['is_verified'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = bcrypt($validated['password']);
        }

        $user->update($updateData);
        $user->roles()->sync($validated['roles']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate user (soft action)
     */
    public function deactivate(User $user): JsonResponse
    {
        $user->update(['is_verified' => false]);

        return response()->json([
            'message' => 'User deactivated successfully.',
        ]);
    }

    /**
     * Activate user
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['is_verified' => true]);

        return response()->json([
            'message' => 'User activated successfully.',
        ]);
    }

    /**
     * Assign roles to a user (API)
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->roles()->attach($validated['role_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully',
            'roles' => $user->roles()->pluck('slug'),
        ]);
    }

    /**
     * Remove a specific role from a user (API)
     */
    public function removeRole(User $user, Role $role): JsonResponse
    {
        $user->roles()->detach($role->id);

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully',
            'roles' => $user->roles()->pluck('slug'),
        ]);
    }

    /**
     * Synchronize roles for a user (API) – replace all
     */
    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($validated['role_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Roles synchronized successfully',
            'roles' => $user->roles()->pluck('slug'),
        ]);
    }

    private function getUserStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_verified', true)->count();
        $pendingInvitations = ProviderInvitation::where('status', 'pending')->count();
        $recentLogins = User::where('last_activity', '>=', Carbon::now()->subDays(7))->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_invitations' => $pendingInvitations,
            'recent_logins' => $recentLogins,
        ];
    }
}
