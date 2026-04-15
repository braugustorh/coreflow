<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sede;
use Illuminate\Auth\Access\HandlesAuthorization;

class SedePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sede');
    }

    public function view(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('View:Sede');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sede');
    }

    public function update(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('Update:Sede');
    }

    public function delete(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('Delete:Sede');
    }

    public function restore(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('Restore:Sede');
    }

    public function forceDelete(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('ForceDelete:Sede');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sede');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sede');
    }

    public function replicate(AuthUser $authUser, Sede $sede): bool
    {
        return $authUser->can('Replicate:Sede');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sede');
    }

}