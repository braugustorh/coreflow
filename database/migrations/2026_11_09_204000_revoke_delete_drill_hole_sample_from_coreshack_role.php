<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'Coreshack')->first();
        if ($role && $role->hasPermissionTo('Delete:DrillHoleSample')) {
            $role->revokePermissionTo('Delete:DrillHoleSample');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'Coreshack')->first();
        if ($role && !$role->hasPermissionTo('Delete:DrillHoleSample')) {
            $role->givePermissionTo('Delete:DrillHoleSample');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
