<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DrillHoleSample;
use Illuminate\Auth\Access\HandlesAuthorization;

class DrillHoleSamplePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DrillHoleSample');
    }

    public function view(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('View:DrillHoleSample');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DrillHoleSample');
    }

    public function update(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('Update:DrillHoleSample');
    }

    public function delete(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('Delete:DrillHoleSample');
    }

    public function restore(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('Restore:DrillHoleSample');
    }

    public function forceDelete(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('ForceDelete:DrillHoleSample');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DrillHoleSample');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DrillHoleSample');
    }

    public function replicate(AuthUser $authUser, DrillHoleSample $drillHoleSample): bool
    {
        return $authUser->can('Replicate:DrillHoleSample');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DrillHoleSample');
    }

}