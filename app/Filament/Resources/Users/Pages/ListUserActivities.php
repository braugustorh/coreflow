<?php

namespace App\Filament\Resources\Users\Pages;

use pxlrbt\FilamentActivityLog\Pages\ListActivities;
use App\Filament\Resources\Users\UserResource;

class ListUserActivities extends ListActivities
{
    protected static string $resource = UserResource::class;
}
