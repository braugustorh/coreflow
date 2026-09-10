<?php

namespace Tests\Feature;

use App\Filament\Pages\AgileCapture;
use App\Filament\Pages\SamplesSettings;
use App\Filament\Resources\AssayMethods\AssayMethodResource;
use App\Filament\Resources\DrillHoleSampleResource;
use App\Filament\Resources\DrillHoles\DrillHoleResource;
use App\Filament\Resources\Elements\ElementResource;
use App\Filament\Resources\Proyectos\ProyectoResource;
use App\Filament\Resources\SamplingMonitor\SamplingMonitorResource;
use App\Filament\Resources\Sedes\SedeResource;
use App\Filament\Resources\StandardSamples\StandardSampleResource;
use App\Filament\Resources\SummarySamplesResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos básicos necesarios para el test
        $permissions = [
            'ViewAny:DrillHoleSample', 'View:DrillHoleSample', 'Create:DrillHoleSample', 'Update:DrillHoleSample', 'Delete:DrillHoleSample',
            'ViewAny:DrillHole', 'View:DrillHole', 'Create:DrillHole', 'Update:DrillHole',
            'ViewAny:WorkOrder', 'View:WorkOrder', 'Create:WorkOrder', 'Update:WorkOrder',
            'ViewAny:StandardSample', 'View:StandardSample', 'Create:StandardSample', 'Update:StandardSample',
            'ViewAny:Sede', 'ViewAny:User', 'ViewAny:Role', 'ViewAny:Proyecto', 'ViewAny:Element', 'ViewAny:AssayMethod',
            'View:AgileCapture', 'View:SamplesSettings',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $supervisor = Role::firstOrCreate(['name' => 'Supervisor CoreS', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'ViewAny:DrillHoleSample', 'View:DrillHoleSample', 'Create:DrillHoleSample', 'Update:DrillHoleSample', 'Delete:DrillHoleSample',
            'ViewAny:DrillHole', 'View:DrillHole', 'Create:DrillHole', 'Update:DrillHole',
            'ViewAny:WorkOrder', 'View:WorkOrder', 'Create:WorkOrder', 'Update:WorkOrder',
            'ViewAny:StandardSample', 'View:StandardSample', 'Create:StandardSample', 'Update:StandardSample',
            'View:AgileCapture', 'View:SamplesSettings',
        ]);

        $geologo = Role::firstOrCreate(['name' => 'Geologo', 'guard_name' => 'web']);
        $geologo->syncPermissions([
            'ViewAny:DrillHoleSample', 'View:DrillHoleSample', 'Create:DrillHoleSample', 'Update:DrillHoleSample', 'Delete:DrillHoleSample',
            'ViewAny:DrillHole', 'View:DrillHole', 'Create:DrillHole', 'Update:DrillHole',
            'View:AgileCapture',
        ]);
    }

    public function test_supervisor_cores_permissions_matrix(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Supervisor CoreS');
        $this->actingAs($user);

        // Permitidos:
        $this->assertTrue(DrillHoleSampleResource::canViewAny(), 'Supervisor debe tener acceso a Validación de CSV');
        $this->assertTrue(AgileCapture::canAccess(), 'Supervisor debe tener acceso a Captura Ágil');
        $this->assertTrue(SamplingMonitorResource::canViewAny(), 'Supervisor debe tener acceso a Monitor de Muestreo');
        $this->assertTrue(SummarySamplesResource::canViewAny(), 'Supervisor debe tener acceso a Summary Samples');
        $this->assertTrue(DrillHoleResource::canViewAny(), 'Supervisor debe tener acceso a Drill Holes');
        $this->assertTrue(WorkOrderResource::canViewAny(), 'Supervisor debe tener acceso a Work Orders');
        $this->assertTrue(StandardSampleResource::canViewAny(), 'Supervisor debe tener acceso a Standard Samples');
        $this->assertTrue(SamplesSettings::canAccess(), 'Supervisor debe tener acceso a Configuración de Muestras');

        // Denegados:
        $this->assertFalse(ProyectoResource::canViewAny(), 'Supervisor NO debe tener acceso a Proyectos');
        $this->assertFalse(SedeResource::canViewAny(), 'Supervisor NO debe tener acceso a Distritos');
        $this->assertFalse(ElementResource::canViewAny(), 'Supervisor NO debe tener acceso a Elements');
        $this->assertFalse(AssayMethodResource::canViewAny(), 'Supervisor NO debe tener acceso a Assay Methods');
        $this->assertFalse(UserResource::canViewAny(), 'Supervisor NO debe tener acceso a Usuarios');
    }

    public function test_geologo_permissions_matrix(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Geologo');
        $this->actingAs($user);

        // Permitidos:
        $this->assertTrue(DrillHoleSampleResource::canViewAny(), 'Geólogo debe tener acceso a Validación de CSV');
        $this->assertTrue(AgileCapture::canAccess(), 'Geólogo debe tener acceso a Captura Ágil');
        $this->assertTrue(DrillHoleResource::canViewAny(), 'Geólogo debe tener acceso a Drill Holes');

        // Denegados:
        $this->assertFalse(SamplingMonitorResource::canViewAny(), 'Geólogo NO debe tener acceso a Monitor de Muestreo');
        $this->assertFalse(SummarySamplesResource::canViewAny(), 'Geólogo NO debe tener acceso a Summary Samples');
        $this->assertFalse(WorkOrderResource::canViewAny(), 'Geólogo NO debe tener acceso a Work Orders');
        $this->assertFalse(StandardSampleResource::canViewAny(), 'Geólogo NO debe tener acceso a Standard Samples');
        $this->assertFalse(ProyectoResource::canViewAny(), 'Geólogo NO debe tener acceso a Proyectos');
        $this->assertFalse(SedeResource::canViewAny(), 'Geólogo NO debe tener acceso a Distritos');
        $this->assertFalse(ElementResource::canViewAny(), 'Geólogo NO debe tener acceso a Elements');
        $this->assertFalse(AssayMethodResource::canViewAny(), 'Geólogo NO debe tener acceso a Assay Methods');
        $this->assertFalse(UserResource::canViewAny(), 'Geólogo NO debe tener acceso a Usuarios');
        $this->assertFalse(SamplesSettings::canAccess(), 'Geólogo NO debe tener acceso a Configuración de Muestras');
    }
}
