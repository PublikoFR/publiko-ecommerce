<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Lunar\Admin\Models\Staff;
use Pko\Pennylane\Models\PennylaneInvoice;

class PennylaneInvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the staff can view any models.
     */
    public function viewAny(Staff $staff): bool
    {
        return $staff->can('view_any_pennylane::invoice');
    }

    /**
     * Determine whether the staff can view the model.
     */
    public function view(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('view_pennylane::invoice');
    }

    /**
     * Determine whether the staff can create models.
     */
    public function create(Staff $staff): bool
    {
        return $staff->can('create_pennylane::invoice');
    }

    /**
     * Determine whether the staff can update the model.
     */
    public function update(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('update_pennylane::invoice');
    }

    /**
     * Determine whether the staff can delete the model.
     */
    public function delete(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('delete_pennylane::invoice');
    }

    /**
     * Determine whether the staff can bulk delete.
     */
    public function deleteAny(Staff $staff): bool
    {
        return $staff->can('delete_any_pennylane::invoice');
    }

    /**
     * Determine whether the staff can permanently delete.
     */
    public function forceDelete(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('force_delete_pennylane::invoice');
    }

    /**
     * Determine whether the staff can permanently bulk delete.
     */
    public function forceDeleteAny(Staff $staff): bool
    {
        return $staff->can('force_delete_any_pennylane::invoice');
    }

    /**
     * Determine whether the staff can restore.
     */
    public function restore(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('restore_pennylane::invoice');
    }

    /**
     * Determine whether the staff can bulk restore.
     */
    public function restoreAny(Staff $staff): bool
    {
        return $staff->can('restore_any_pennylane::invoice');
    }

    /**
     * Determine whether the staff can replicate.
     */
    public function replicate(Staff $staff, PennylaneInvoice $pennylaneInvoice): bool
    {
        return $staff->can('replicate_pennylane::invoice');
    }

    /**
     * Determine whether the staff can reorder.
     */
    public function reorder(Staff $staff): bool
    {
        return $staff->can('reorder_pennylane::invoice');
    }
}
