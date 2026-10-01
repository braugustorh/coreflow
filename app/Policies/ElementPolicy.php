<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Element;
use Illuminate\Auth\Access\HandlesAuthorization;

class ElementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Element');
    }

    public function view(AuthUser $authUser, Element $element): bool
    {
        return $authUser->can('View:Element');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Element');
    }

    public function update(AuthUser $authUser, Element $element): bool
    {
        return $authUser->can('Update:Element');
    }

    public function delete(AuthUser $authUser, Element $element): bool
    {
        return $authUser->can('Delete:Element');
    }

    public function restore(AuthUser $authUser, Element $element): bool
    {
        return $authUser->can('Restore:Element');
    }

    public function forceDelete(AuthUser $authUser, Element $element): bool
    {
        return $authUser->can('ForceDelete:Element');
    }
}
