<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Lunar\Shipping\Models\ShippingZone;

class ShippingZonePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_shipping::zone');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('view_shipping::zone');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_shipping::zone');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('update_shipping::zone');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('delete_shipping::zone');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_shipping::zone');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('force_delete_shipping::zone');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_shipping::zone');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('restore_shipping::zone');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_shipping::zone');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, ShippingZone $shippingZone): bool
    {
        return $staff->can('replicate_shipping::zone');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_shipping::zone');
    }
}
