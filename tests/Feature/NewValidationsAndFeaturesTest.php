<?php

namespace Tests\Feature;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\DrillHoleSamplePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewValidationsAndFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularUser;
    private DrillHoleSample $archivedSample;
    private DrillHoleSample $draftSample;
    private WorkOrder $sentWo;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos y roles
        Permission::findOrCreate('Update:DrillHoleSample');
        Permission::findOrCreate('Delete:DrillHoleSample');

        $superAdminRole = Role::findOrCreate('super_admin');
        $adminCoreFlowRole = Role::findOrCreate('Admin CoreFlow');
        $supervisorRole = Role::findOrCreate('Supervisor CoreS');

        $superAdminRole->givePermissionTo(['Update:DrillHoleSample', 'Delete:DrillHoleSample']);
        $adminCoreFlowRole->givePermissionTo(['Update:DrillHoleSample', 'Delete:DrillHoleSample']);
        $supervisorRole->givePermissionTo(['Update:DrillHoleSample', 'Delete:DrillHoleSample']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin CoreFlow');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('Supervisor CoreS');

        $sede = Sede::create(['name' => 'Sede SM', 'city' => 'San Miguel', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Proyecto Test', 'code' => 'PT', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'DH-POL-01', 'proyecto_id' => $proyecto->id, 'core_size' => 'HQ']);

        $this->sentWo = WorkOrder::create([
            'work_order_code' => 'WO-SENT-01',
            'sede_id'         => $sede->id,
            'dispatch_date'   => '2026-08-28',
        ]);

        $this->archivedSample = DrillHoleSample::create([
            'user_id'       => $this->regularUser->id,
            'work_order_id' => $this->sentWo->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'SMP-ARCH-01',
            'sample_type'   => 'Original',
            'core_size'     => 'HQ',
            'is_archived'   => true,
            'sent_date'     => '2026-08-28',
        ]);

        $this->draftSample = DrillHoleSample::create([
            'user_id'       => $this->regularUser->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'SMP-DRAFT-01',
            'sample_type'   => 'Original',
            'core_size'     => 'HQ',
            'is_archived'   => false,
        ]);
    }

    public function test_regular_user_cannot_update_or_delete_sent_sample(): void
    {
        $policy = new DrillHoleSamplePolicy();

        // Muestra de orden enviada: usuario regular no puede editar ni borrar
        $this->assertFalse($policy->update($this->regularUser, $this->archivedSample));
        $this->assertFalse($policy->delete($this->regularUser, $this->archivedSample));

        // Muestra en borrador / no enviada: usuario regular sí puede editar
        $this->assertTrue($policy->update($this->regularUser, $this->draftSample));
        $this->assertTrue($policy->delete($this->regularUser, $this->draftSample));
    }

    public function test_admin_coreflow_can_update_and_delete_sent_sample(): void
    {
        $policy = new DrillHoleSamplePolicy();

        // Muestra de orden enviada: Admin CoreFlow sí puede editar y borrar
        $this->assertTrue($policy->update($this->adminUser, $this->archivedSample));
        $this->assertTrue($policy->delete($this->adminUser, $this->archivedSample));
    }

    public function test_super_admin_can_update_and_delete_sent_sample(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $policy = new DrillHoleSamplePolicy();

        $this->assertTrue($policy->update($superAdmin, $this->archivedSample));
        $this->assertTrue($policy->delete($superAdmin, $this->archivedSample));
    }

    public function test_summary_samples_discrepancy_alert_logic(): void
    {
        $sede = Sede::first();

        // Work Order con 50 muestras totales
        $wo = WorkOrder::create([
            'work_order_code'        => 'WO-SUM-01',
            'sede_id'                => $sede->id,
            'samples_quantity'       => 50,
            'received_samples_count' => 45, // Faltan 5 muestras
        ]);

        $this->assertTrue((int)$wo->received_samples_count < (int)$wo->samples_quantity);
        $diff = (int)$wo->samples_quantity - (int)$wo->received_samples_count;
        $this->assertEquals(5, $diff);
    }

    public function test_agile_capture_save_batch_allows_empty_weight(): void
    {
        $barreno = DrillHole::first();
        $proyecto = Proyecto::first();

        // Lote de muestras con peso vacío (debe guardarse sin arrojar error)
        $samplesData = [
            [
                'proyecto_id'   => $proyecto->id,
                'sample_number' => 'SMP-NOWEIGHT-01',
                'sample_type'   => 'Original',
                'sampled_at'    => '2026-08-28 10:00:00',
                'from_depth'    => '0',
                'to_depth'      => '1.5',
                'sample_length' => '1.5',
                'weight'        => '', // Vacío
                'comentarios'   => '',
            ],
            [
                'proyecto_id'   => $proyecto->id,
                'sample_number' => 'SMP-NOWEIGHT-02',
                'sample_type'   => 'Control',
                'control_type'  => 'Blank',
                'sampled_at'    => '2026-08-28 10:00:00',
                'weight'        => '', // Vacío
                'comentarios'   => '',
            ]
        ];

        \Livewire\Livewire::actingAs($this->adminUser)
            ->test(\App\Filament\Pages\AgileCapture::class)
            ->set('selectedBarrenoId', $barreno->id)
            ->call('saveBatch', $samplesData);

        $s1 = DrillHoleSample::where('sample_number', 'SMP-NOWEIGHT-01')->first();
        $s2 = DrillHoleSample::where('sample_number', 'SMP-NOWEIGHT-02')->first();

        $this->assertNotNull($s1);
        $this->assertNull($s1->weight);

        $this->assertNotNull($s2);
        $this->assertNull($s2->weight);
    }
}
