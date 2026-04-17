<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductType;

class ProductTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_mde::product::type');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('view_mde::product::type');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_mde::product::type');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('update_mde::product::type');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('delete_mde::product::type');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_mde::product::type');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('force_delete_mde::product::type');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_mde::product::type');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('restore_mde::product::type');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_mde::product::type');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, ProductType $productType): bool
    {
        return $staff->can('replicate_mde::product::type');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_mde::product::type');
    }
}
