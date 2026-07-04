<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;

class StaffPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_staff');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff): bool
    {
        return $staff->can('view_staff');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_staff');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff): bool
    {
        return $staff->can('update_staff');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff): bool
    {
        return $staff->can('delete_staff');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_staff');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff): bool
    {
        return $staff->can('force_delete_staff');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_staff');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff): bool
    {
        return $staff->can('restore_staff');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_staff');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function replicate(Staff $staff): bool
    {
        return $staff->can('replicate_staff');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_staff');
    }
}
