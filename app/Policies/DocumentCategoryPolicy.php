<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Pko\ProductDocuments\Models\DocumentCategory;

class DocumentCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_document::category');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('view_document::category');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_document::category');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('update_document::category');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('delete_document::category');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_document::category');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('force_delete_document::category');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_document::category');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('restore_document::category');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_document::category');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, DocumentCategory $documentCategory): bool
    {
        return $staff->can('replicate_document::category');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_document::category');
    }
}
