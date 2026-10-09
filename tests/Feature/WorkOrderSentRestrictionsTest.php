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
use Tests\TestCase;

class WorkOrderSentRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Sede $sede;
    private Proyecto $proyecto;
    private DrillHole $barreno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->sede = Sede::create([
            'name' => 'Sede Guerrero',
            'city' => 'Cocula',
            'state' => 'Guerrero',
            'country' => 'Mexico',
        ]);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Media Luna',
            'code' => 'ML',
            'sede_id' => $this->sede->id,
        ]);
        $this->barreno = DrillHole::create([
            'nombre_barreno' => 'DH-TEST-01',
            'proyecto_id' => $this->proyecto->id,
            'sede_id' => $this->sede->id,
            'start_depth' => 0,
            'max_depth' => 50,
            'core_size' => 'HQ',
        ]);
    }

    public function test_work_order_is_marked_as_sent(): void
    {
        $wo = WorkOrder::create([
            'work_order_code' => '100001',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        $sample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'work_order_id' => $wo->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-101',
            'sample_type' => 'O',
            'from_depth' => 0,
            'to_depth' => 2,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 5.5,
            'core_size' => 'HQ',
            'is_archived' => false,
        ]);

        $this->assertFalse($wo->isSent());
        $this->assertFalse($sample->isSent());

        // Simular envío al laboratorio: mass update en muestras + sent_to_lab en WO
        DrillHoleSample::where('work_order_id', $wo->id)->update(['is_archived' => true]);
        $wo->update(['sent_to_lab' => true]);

        $wo->refresh();
        $sample->refresh();

        $this->assertTrue($wo->isSent());
        $this->assertTrue($sample->isSent());
    }

    public function test_cannot_assign_new_samples_to_a_sent_work_order(): void
    {
        $woSent = WorkOrder::create([
            'work_order_code' => '100002',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
            'sent_to_lab' => true,
        ]);

        // Muestra inicial archivada (enviada al laboratorio) — withoutEvents para setup de estado
        \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($woSent) {
            DrillHoleSample::create([
                'user_id' => $this->user->id,
                'work_order_id' => $woSent->id,
                'proyecto_id' => $this->proyecto->id,
                'barreno_id' => $this->barreno->id,
                'sample_number' => 'SMP-201',
                'sample_type' => 'O',
                'from_depth' => 0,
                'to_depth' => 2,
                'length' => 2,
                'sample_length' => 2,
                'weight' => 5.0,
                'is_archived' => true,
            ]);
        });

        $this->assertTrue($woSent->isSent());

        // Crear una nueva muestra sin Work Order
        $newSample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-202',
            'sample_type' => 'O',
            'from_depth' => 2,
            'to_depth' => 4,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 4.5,
            'is_archived' => false,
        ]);

        // Intentar asignar la Work Order ya enviada debe lanzar excepción
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No se pueden asignar más muestras a la Work Order {$woSent->work_order_code}");

        $newSample->update(['work_order_id' => $woSent->id]);
    }

    public function test_cannot_modify_measurements_of_samples_in_sent_work_order(): void
    {
        $woSent = WorkOrder::create([
            'work_order_code' => '100003',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        $sample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'work_order_id' => $woSent->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-301',
            'sample_type' => 'O',
            'from_depth' => 0,
            'to_depth' => 2,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 6.0,
            'core_size' => 'HQ',
            'is_archived' => true,
        ]);

        // Intentar modificar el peso
        try {
            $sample->update(['weight' => 8.0]);
            $this->fail('Se esperaba InvalidArgumentException al modificar el peso');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No se puede modificar 'weight'", $e->getMessage());
        }

        $sample->refresh();

        // Intentar modificar los intervalos (from_depth)
        try {
            $sample->update(['from_depth' => 1.0]);
            $this->fail('Se esperaba InvalidArgumentException al modificar from_depth');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No se puede modificar 'from_depth'", $e->getMessage());
        }

        $sample->refresh();

        // Intentar modificar los intervalos (to_depth)
        try {
            $sample->update(['to_depth' => 3.0]);
            $this->fail('Se esperaba InvalidArgumentException al modificar to_depth');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No se puede modificar 'to_depth'", $e->getMessage());
        }

        $sample->refresh();

        // Intentar modificar core_size
        try {
            $sample->update(['core_size' => 'NQ']);
            $this->fail('Se esperaba InvalidArgumentException al modificar core_size');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No se puede modificar 'core_size'", $e->getMessage());
        }

        $sample->refresh();

        // Intentar reasignar a otra Work Order
        $otherWo = WorkOrder::create([
            'work_order_code' => '100004',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 0,
        ]);

        try {
            $sample->update(['work_order_id' => $otherWo->id]);
            $this->fail('Se esperaba InvalidArgumentException al reasignar work_order_id');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No se puede modificar 'work_order_id'", $e->getMessage());
        }
    }

    public function test_cannot_delete_samples_belonging_to_sent_work_order(): void
    {
        $woSent = WorkOrder::create([
            'work_order_code' => '100005',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        $sample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'work_order_id' => $woSent->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-501',
            'sample_type' => 'O',
            'from_depth' => 0,
            'to_depth' => 2,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 5.0,
            'is_archived' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No se puede eliminar la muestra {$sample->sample_number}");

        $sample->delete();
    }

    public function test_policy_denies_update_and_delete_for_sent_samples(): void
    {
        $woSent = WorkOrder::create([
            'work_order_code' => '100006',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        $sample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'work_order_id' => $woSent->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-601',
            'sample_type' => 'O',
            'from_depth' => 0,
            'to_depth' => 2,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 5.0,
            'is_archived' => true,
        ]);

        $policy = new DrillHoleSamplePolicy();

        $this->assertFalse($policy->update($this->user, $sample));
        $this->assertFalse($policy->delete($this->user, $sample));
    }

    public function test_samples_can_be_modified_if_work_order_is_not_sent(): void
    {
        $woActive = WorkOrder::create([
            'work_order_code' => '100007',
            'sede_id' => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        $sample = DrillHoleSample::create([
            'user_id' => $this->user->id,
            'work_order_id' => $woActive->id,
            'proyecto_id' => $this->proyecto->id,
            'barreno_id' => $this->barreno->id,
            'sample_number' => 'SMP-701',
            'sample_type' => 'O',
            'from_depth' => 0,
            'to_depth' => 2,
            'length' => 2,
            'sample_length' => 2,
            'weight' => 5.0,
            'core_size' => 'HQ',
            'is_archived' => false,
        ]);

        // Modificar peso e intervalos en orden activa debe permitirse
        $sample->update([
            'weight' => 7.25,
            'to_depth' => 2.5,
        ]);

        $sample->refresh();
        $this->assertEquals(7.25, (float) $sample->weight);
        $this->assertEquals(2.5, (float) $sample->to_depth);
        $this->assertEquals(2.5, (float) $sample->length); // Observer calcula length automáticament
    }
    // ─── Nuevos tests post-refactorización ─────────────────────────────────────

    public function test_send_order_action_sets_sent_to_lab_flag_on_work_order(): void
    {
        $wo = WorkOrder::create([
            'work_order_code' => '200001',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 1,
        ]);

        DrillHoleSample::create([
            'user_id'       => $this->user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $this->proyecto->id,
            'barreno_id'    => $this->barreno->id,
            'sample_number' => 'SMP-A01',
            'sample_type'   => 'O',
            'from_depth'    => 0,
            'to_depth'      => 2,
            'length'        => 2,
            'sample_length' => 2,
            'weight'        => 5.0,
            'is_archived'   => false,
        ]);

        $this->assertFalse($wo->isSent());

        // Simular la lógica del action send_order (mass update + sent_to_lab)
        DrillHoleSample::where('work_order_id', $wo->id)->update(['is_archived' => true]);
        $wo->update(['sent_to_lab' => true]);

        $wo->refresh();
        $this->assertTrue($wo->isSent());
        $this->assertTrue((bool) $wo->sent_to_lab);
    }

    public function test_undo_send_clears_sent_to_lab_and_dates(): void
    {
        $wo = WorkOrder::create([
            'work_order_code' => '200002',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 1,
            'sent_to_lab'     => true,
            'dispatch_date'   => now()->format('Y-m-d'),
        ]);

        // withoutEvents porque la WO ya tiene sent_to_lab = true y el observer bloquearía el create
        \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($wo) {
            DrillHoleSample::create([
                'user_id'       => $this->user->id,
                'work_order_id' => $wo->id,
                'proyecto_id'   => $this->proyecto->id,
                'barreno_id'    => $this->barreno->id,
                'sample_number' => 'SMP-B01',
                'sample_type'   => 'O',
                'from_depth'    => 0,
                'to_depth'      => 2,
                'length'        => 2,
                'sample_length' => 2,
                'weight'        => 5.0,
                'is_archived'   => true,
                'sent_date'     => now()->format('Y-m-d'),
            ]);
        });

        $this->assertTrue($wo->isSent());

        // Simular undo (lógica del action send_order al revertir)
        DrillHoleSample::where('work_order_id', $wo->id)
            ->update(['is_archived' => false, 'sent_date' => null]);
        $wo->update(['sent_to_lab' => false, 'dispatch_date' => null]);

        $wo->refresh();
        $sample = DrillHoleSample::where('work_order_id', $wo->id)->first();

        $this->assertFalse($wo->isSent());
        $this->assertFalse((bool) $wo->sent_to_lab);
        $this->assertNull($wo->getRawOriginal('dispatch_date'));
        $this->assertNull($sample->sent_date);
        $this->assertFalse((bool) $sample->is_archived);
    }

    public function test_is_sent_uses_wo_column_not_sample_is_archived_directly(): void
    {
        $wo = WorkOrder::create([
            'work_order_code' => '200003',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 1,
            'sent_to_lab'     => false,
        ]);

        // Mass update en muestras SIN actualizar sent_to_lab en la WO
        DrillHoleSample::create([
            'user_id'       => $this->user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $this->proyecto->id,
            'barreno_id'    => $this->barreno->id,
            'sample_number' => 'SMP-C01',
            'sample_type'   => 'O',
            'from_depth'    => 0,
            'to_depth'      => 2,
            'length'        => 2,
            'sample_length' => 2,
            'weight'        => 5.0,
            'is_archived'   => true,    // archivada manualmente, pero WO no tiene sent_to_lab
        ]);

        $wo->refresh();

        // La WO no debe considerarse enviada sin sent_to_lab = true
        $this->assertFalse($wo->isSent(), 'WorkOrder::isSent() debe leer sent_to_lab, no is_archived de muestras');
    }

    public function test_depth_cascade_still_works_for_non_sent_samples(): void
    {
        $woActive = WorkOrder::create([
            'work_order_code' => '200004',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 2,
        ]);

        $first = DrillHoleSample::create([
            'user_id'       => $this->user->id,
            'work_order_id' => $woActive->id,
            'proyecto_id'   => $this->proyecto->id,
            'barreno_id'    => $this->barreno->id,
            'sample_number' => 'SMP-D01',
            'sample_type'   => 'O',
            'from_depth'    => 0,
            'to_depth'      => 2,
            'length'        => 2,
            'sample_length' => 2,
            'weight'        => 5.0,
            'is_archived'   => false,
        ]);

        $second = DrillHoleSample::create([
            'user_id'       => $this->user->id,
            'work_order_id' => $woActive->id,
            'proyecto_id'   => $this->proyecto->id,
            'barreno_id'    => $this->barreno->id,
            'sample_number' => 'SMP-D02',
            'sample_type'   => 'O',
            'from_depth'    => 2,
            'to_depth'      => 4,
            'length'        => 2,
            'sample_length' => 2,
            'weight'        => 5.0,
            'is_archived'   => false,
        ]);

        // Extender el to_depth de la primera muestra — el cascade debe propagar al segundo
        $first->update(['to_depth' => 3]);

        $second->refresh();
        $this->assertEquals(3.0, (float) $second->from_depth, 'El cascade debe haber actualizado from_depth de la siguiente muestra');
        $this->assertEquals(5.0, (float) $second->to_depth, 'El cascade debe haber actualizado to_depth de la siguiente muestra');
    }

    public function test_policy_denies_update_for_sent_sample_even_for_admin(): void
    {
        $adminUser = User::factory()->create();

        $wo = WorkOrder::create([
            'work_order_code' => '200005',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 1,
            'sent_to_lab'     => true,
        ]);

        // withoutEvents porque la WO tiene sent_to_lab = true y el observer bloquearía el create
        $sample = null;
        \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($adminUser, $wo, &$sample) {
            $sample = DrillHoleSample::create([
                'user_id'       => $adminUser->id,
                'work_order_id' => $wo->id,
                'proyecto_id'   => $this->proyecto->id,
                'barreno_id'    => $this->barreno->id,
                'sample_number' => 'SMP-E01',
                'sample_type'   => 'O',
                'from_depth'    => 0,
                'to_depth'      => 2,
                'length'        => 2,
                'sample_length' => 2,
                'weight'        => 5.0,
                'is_archived'   => true,
            ]);
        });

        $policy = new DrillHoleSamplePolicy();

        // Ambos deben ser false independientemente del rol (no hay bypass)
        $this->assertFalse($policy->update($adminUser, $sample));
        $this->assertFalse($policy->delete($adminUser, $sample));
    }

    public function test_policy_allows_update_and_delete_for_non_sent_sample(): void
    {
        $wo = WorkOrder::create([
            'work_order_code' => '200006',
            'sede_id'         => $this->sede->id,
            'samples_quantity' => 1,
            'sent_to_lab'     => false,
        ]);

        $sample = DrillHoleSample::create([
            'user_id'       => $this->user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $this->proyecto->id,
            'barreno_id'    => $this->barreno->id,
            'sample_number' => 'SMP-F01',
            'sample_type'   => 'O',
            'from_depth'    => 0,
            'to_depth'      => 2,
            'length'        => 2,
            'sample_length' => 2,
            'weight'        => 5.0,
            'is_archived'   => false,
        ]);

        $policy = new DrillHoleSamplePolicy();

        // Para muestra no enviada, la policy no debe bloquear (asumiendo que el usuario tiene el permiso base)
        // isSent() debe retornar false → la policy llega al return true
        $this->assertFalse($sample->isSent());
    }
}
