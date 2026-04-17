<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Mde\Loyalty\Models\GiftHistory;

class GiftHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_gift::history');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('view_gift::history');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_gift::history');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('update_gift::history');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('delete_gift::history');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_gift::history');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('force_delete_gift::history');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_gift::history');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('restore_gift::history');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_gift::history');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, GiftHistory $giftHistory): bool
    {
        return $staff->can('replicate_gift::history');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_gift::history');
    }
}
