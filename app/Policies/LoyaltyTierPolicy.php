<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Mde\Loyalty\Models\LoyaltyTier;

class LoyaltyTierPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_loyalty::tier');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('view_loyalty::tier');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_loyalty::tier');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('update_loyalty::tier');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('delete_loyalty::tier');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_loyalty::tier');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('force_delete_loyalty::tier');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_loyalty::tier');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('restore_loyalty::tier');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_loyalty::tier');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, LoyaltyTier $loyaltyTier): bool
    {
        return $staff->can('replicate_loyalty::tier');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_loyalty::tier');
    }
}
