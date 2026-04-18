<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Pko\StorefrontCms\Models\HomeTile;

class HomeTilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_home::tile');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('view_home::tile');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_home::tile');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('update_home::tile');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('delete_home::tile');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_home::tile');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('force_delete_home::tile');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_home::tile');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('restore_home::tile');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_home::tile');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, HomeTile $homeTile): bool
    {
        return $staff->can('replicate_home::tile');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_home::tile');
    }
}
