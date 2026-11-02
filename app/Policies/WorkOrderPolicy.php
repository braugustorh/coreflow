<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkOrder');
    }

    public function view(AuthUser $authUser, WorkOrder $workOrder): bool
    {
        return $authUser->can('View:WorkOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkOrder');
    }

    public function update(AuthUser $authUser, WorkOrder $workOrder): bool
    {
        return $authUser->can('Update:WorkOrder');
    }

    /**
     * Solo super_admin y Admin CoreFlow pueden eliminar Work Orders.
     */
    public function delete(AuthUser $authUser, WorkOrder $workOrder): bool
    {
        return WorkOrder::userCanManageAssociations($authUser);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return WorkOrder::userCanManageAssociations($authUser);
    }

    public function restore(AuthUser $authUser, WorkOrder $workOrder): bool
    {
        return $authUser->can('Restore:WorkOrder');
    }

    public function forceDelete(AuthUser $authUser, WorkOrder $workOrder): bool
    {
        return $authUser->can('ForceDelete:WorkOrder');
    }
}
