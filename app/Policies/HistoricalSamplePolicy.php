<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HistoricalSample;
use Illuminate\Auth\Access\HandlesAuthorization;

class HistoricalSamplePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HistoricalSample');
    }

    public function view(AuthUser $authUser, HistoricalSample $historicalSample): bool
    {
        return $authUser->can('View:HistoricalSample');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HistoricalSample');
    }

    public function update(AuthUser $authUser, HistoricalSample $historicalSample): bool
    {
        return $authUser->can('Update:HistoricalSample');
    }

    public function delete(AuthUser $authUser, HistoricalSample $historicalSample): bool
    {
        return $authUser->can('Delete:HistoricalSample');
    }

    public function restore(AuthUser $authUser, HistoricalSample $historicalSample): bool
    {
        return $authUser->can('Restore:HistoricalSample');
    }

    public function forceDelete(AuthUser $authUser, HistoricalSample $historicalSample): bool
    {
        return $authUser->can('ForceDelete:HistoricalSample');
    }
}
