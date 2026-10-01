<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AssayMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssayMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssayMethod');
    }

    public function view(AuthUser $authUser, AssayMethod $assayMethod): bool
    {
        return $authUser->can('View:AssayMethod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssayMethod');
    }

    public function update(AuthUser $authUser, AssayMethod $assayMethod): bool
    {
        return $authUser->can('Update:AssayMethod');
    }

    public function delete(AuthUser $authUser, AssayMethod $assayMethod): bool
    {
        return $authUser->can('Delete:AssayMethod');
    }

    public function restore(AuthUser $authUser, AssayMethod $assayMethod): bool
    {
        return $authUser->can('Restore:AssayMethod');
    }

    public function forceDelete(AuthUser $authUser, AssayMethod $assayMethod): bool
    {
        return $authUser->can('ForceDelete:AssayMethod');
    }
}
