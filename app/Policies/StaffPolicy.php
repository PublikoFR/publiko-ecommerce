<?php

namespace App\Policies;

use Lunar\Admin\Models\Staff;

use Illuminate\Auth\Access\HandlesAuthorization;

class StaffPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_staff');
    }

    /**
     * Determine whether the staff can view the model.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function view(Staff $staff): bool
    {
        return $staff->can('view_staff');
    }

    /**
     * Determine whether the staff can create models.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_staff');
    }

    /**
     * Determine whether the staff can update the model.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function update(Staff $staff): bool
    {
        return $staff->can('update_staff');
    }

    /**
     * Determine whether the staff can delete the model.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function delete(Staff $staff): bool
    {
        return $staff->can('delete_staff');
    }

    /**
     * Determine whether the staff can bulk delete.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_staff');
    }

    /**
     * Determine whether the staff can permanently delete.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function forceDelete(Staff $staff): bool
    {
        return $staff->can('force_delete_staff');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_staff');
    }

    /**
     * Determine whether the staff can restore.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function restore(Staff $staff): bool
    {
        return $staff->can('restore_staff');
    }

    /**
     * Determine whether the staff can bulk restore.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_staff');
    }

    /**
     * Determine whether the staff can bulk restore.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function replicate(Staff $staff): bool
    {
        return $staff->can('replicate_staff');
    }

    /**
     * Determine whether the staff can reorder.
     *
     * @param  \Lunar\Admin\Models\Staff  $staff
     * @return bool
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_staff');
    }
}
