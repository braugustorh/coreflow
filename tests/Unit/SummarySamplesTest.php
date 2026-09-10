<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummarySamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_with_samples_only_returns_work_orders_with_assigned_samples(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Test', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);

        $woWithSamples = WorkOrder::create([
            'work_order_code' => 'WO-100',
            'sede_id'         => $sede->id,
            'samples_quantity' => 3,
        ]);

        $woEmpty = WorkOrder::create([
            'work_order_code' => 'WO-200',
            'sede_id'         => $sede->id,
            'samples_quantity' => 0,
        ]);

        $proyecto = Proyecto::create(['nombre' => 'Proyecto Test', 'code' => 'PRJ', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'DDH-001', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 100]);

        DrillHoleSample::create([
            'user_id'       => $user->id,
            'work_order_id' => $woWithSamples->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'SMP-001',
            'sample_type'   => 'O',
        ]);

        $results = WorkOrder::withSamples()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('WO-100', $results->first()->work_order_code);
    }

    public function test_turnaround_time_and_sample_type_counts_are_calculated_correctly(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Test 2', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Proyecto Alfa', 'code' => 'ALF', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'BH-55', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 200]);

        $wo = WorkOrder::create([
            'work_order_code'        => 'WO-300',
            'sede_id'                => $sede->id,
            'dispatch_date'          => '2026-08-01',
            'reception_date'         => '2026-08-10',
            'received_samples_count' => 3,
            'status'                 => 'En revisión QaQc',
            'comments'               => 'Todo en orden',
        ]);

        // Muestra Original
        DrillHoleSample::create([
            'user_id'       => $user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'ORIG-01',
            'sample_type'   => 'O',
        ]);

        // Muestra QC Standard
        DrillHoleSample::create([
            'user_id'       => $user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'STD-01',
            'sample_type'   => 'CONTROL',
            'control_type'  => 'STANDARD',
        ]);

        // Muestra PULP Blank
        DrillHoleSample::create([
            'user_id'       => $user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'BLK-01',
            'sample_type'   => 'CONTROL',
            'control_type'  => 'BLANK_COARSE',
        ]);

        $this->assertEquals(9, $wo->turnaround_time);
        $this->assertEquals(1, $wo->original_samples_count);
        $this->assertEquals(2, $wo->qc_samples_count);
        $this->assertEquals(1, $wo->pulp_samples_count);
        $this->assertEquals('BH-55', $wo->barrenos_list);
        $this->assertEquals('Proyecto Alfa', $wo->proyectos_list);
    }

    public function test_turnaround_time_uses_sent_date_from_sampling_monitor(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Test 3', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Proyecto Beta', 'code' => 'BET', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'BH-88', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 200]);

        // WO sin dispatch_date en work_orders
        $wo = WorkOrder::create([
            'work_order_code' => 'WO-MONITOR-01',
            'sede_id'         => $sede->id,
            'dispatch_date'   => null,
            'reception_date'  => null,
        ]);

        // Muestra con sent_date capturada en el Monitor de Muestreo (hace 4 días)
        $sentDate = now()->subDays(4)->format('Y-m-d');
        DrillHoleSample::create([
            'user_id'       => $user->id,
            'work_order_id' => $wo->id,
            'proyecto_id'   => $proyecto->id,
            'barreno_id'    => $barreno->id,
            'sample_number' => 'MON-01',
            'sample_type'   => 'O',
            'sent_date'     => $sentDate,
        ]);

        // Debe heredar la fecha de envío del monitor de muestra
        $this->assertEquals($sentDate, $wo->dispatch_date?->format('Y-m-d'));
        // El turnaround time debe ser 4 días (de hace 4 días a hoy)
        $this->assertEquals(4, $wo->turnaround_time);

        // Si se recibe hoy, turnaround_time sigue siendo 4 días
        $wo->reception_date = now()->format('Y-m-d');
        $wo->save();
        $this->assertEquals(4, $wo->fresh()->turnaround_time);
    }
}
