<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StandardSample;
use Illuminate\Auth\Access\HandlesAuthorization;

class StandardSamplePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StandardSample');
    }

    public function view(AuthUser $authUser, StandardSample $standardSample): bool
    {
        return $authUser->can('View:StandardSample');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StandardSample');
    }

    public function update(AuthUser $authUser, StandardSample $standardSample): bool
    {
        return $authUser->can('Update:StandardSample');
    }

    public function delete(AuthUser $authUser, StandardSample $standardSample): bool
    {
        return $authUser->can('Delete:StandardSample');
    }

    public function restore(AuthUser $authUser, StandardSample $standardSample): bool
    {
        return $authUser->can('Restore:StandardSample');
    }

    public function forceDelete(AuthUser $authUser, StandardSample $standardSample): bool
    {
        return $authUser->can('ForceDelete:StandardSample');
    }
}
