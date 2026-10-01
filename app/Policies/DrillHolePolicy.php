<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DrillHole;
use Illuminate\Auth\Access\HandlesAuthorization;

class DrillHolePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DrillHole');
    }

    public function view(AuthUser $authUser, DrillHole $drillHole): bool
    {
        return $authUser->can('View:DrillHole');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DrillHole');
    }

    public function update(AuthUser $authUser, DrillHole $drillHole): bool
    {
        return $authUser->can('Update:DrillHole');
    }

    public function delete(AuthUser $authUser, DrillHole $drillHole): bool
    {
        return $authUser->can('Delete:DrillHole');
    }

    public function restore(AuthUser $authUser, DrillHole $drillHole): bool
    {
        return $authUser->can('Restore:DrillHole');
    }

    public function forceDelete(AuthUser $authUser, DrillHole $drillHole): bool
    {
        return $authUser->can('ForceDelete:DrillHole');
    }
}
