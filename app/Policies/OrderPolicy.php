<?php

namespace App\Policies;

use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-orders');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        if ($order->provider_id === $user->id) {
            return true;
        }
        return $user->hasPermissionTo('view-orders') && $user->facilities->contains($order->facility_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-orders');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        if ($order->provider_id === $user->id) {
            return true;
        }
        return $user->hasPermissionTo('manage-orders') && $user->facilities->contains($order->facility_id);
    }

    /**
     * Determine whether the user can add a checklist to the order.
     */
    public function addChecklist(User $user, Order $order): bool
    {
        if ($order->provider_id === $user->id) {
            return true;
        }

        if ($user->facilities->contains($order->facility_id)) {
            if ($user->hasRole('office-manager') || $user->hasRole('provider') || $user->hasPermissionTo('submit-order-checklist')) {
                return true;
            }
        }
        
        if ($user->hasRole('msc-admin') || $user->hasRole('super-admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('delete-orders') && $order->status === 'draft';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('manage-deleted-orders');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('force-delete-orders');
    }
}
