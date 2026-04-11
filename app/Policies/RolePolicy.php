<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_role');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, Role $role): bool
    {
        return $staff->can('view_role');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_role');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, Role $role): bool
    {
        return $staff->can('update_role');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, Role $role): bool
    {
        return $staff->can('delete_role');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_role');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, Role $role): bool
    {
        return $staff->can('{{ ForceDelete }}');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('{{ ForceDeleteAny }}');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, Role $role): bool
    {
        return $staff->can('{{ Restore }}');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('{{ RestoreAny }}');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, Role $role): bool
    {
        return $staff->can('{{ Replicate }}');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('{{ Reorder }}');
    }
}
