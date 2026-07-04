<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductVariant;

class ProductVariantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_product::variant');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('view_product::variant');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_product::variant');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('update_product::variant');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('delete_product::variant');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_product::variant');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('force_delete_product::variant');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_product::variant');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('restore_product::variant');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_product::variant');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, ProductVariant $productVariant): bool
    {
        return $staff->can('replicate_product::variant');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_product::variant');
    }
}
