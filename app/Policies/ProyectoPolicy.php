<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Proyecto;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProyectoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Proyecto');
    }

    public function view(AuthUser $authUser, Proyecto $proyecto): bool
    {
        return $authUser->can('View:Proyecto');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Proyecto');
    }

    public function update(AuthUser $authUser, Proyecto $proyecto): bool
    {
        return $authUser->can('Update:Proyecto');
    }

    public function delete(AuthUser $authUser, Proyecto $proyecto): bool
    {
        return $authUser->can('Delete:Proyecto');
    }

    public function restore(AuthUser $authUser, Proyecto $proyecto): bool
    {
        return $authUser->can('Restore:Proyecto');
    }

    public function forceDelete(AuthUser $authUser, Proyecto $proyecto): bool
    {
        return $authUser->can('ForceDelete:Proyecto');
    }
}
