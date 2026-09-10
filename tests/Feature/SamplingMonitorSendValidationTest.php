<?php

namespace Tests\Feature;

use App\Filament\Resources\SamplingMonitor\Tables\SamplingMonitorTable;
use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SamplingMonitorSendValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function getSendAction()
    {
        $page = new \App\Filament\Resources\SamplingMonitor\Pages\ListSamplingMonitor();
        $table = Table::make($page);
        $configured = SamplingMonitorTable::configure($table);
        return $configured->getAction('send_order');
    }

    public function test_detects_missing_supervisor_and_sampling_status(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Guerrero', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna', 'code' => 'ML', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'DH-101', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 10, 'core_size' => 'HQ']);

        $wo = WorkOrder::create([
            'work_order_code' => 'WO-VAL-01',
            'sede_id'         => $sede->id,
        ]);

        DrillHoleSample::create([
            'user_id'                => $user->id,
            'work_order_id'          => $wo->id,
            'proyecto_id'            => $proyecto->id,
            'barreno_id'             => $barreno->id,
            'sample_number'          => 'SMP-001',
            'sample_type'            => 'Original',
            'core_size'              => 'HQ',
            'from_depth'             => 0,
            'to_depth'               => 10,
            'responsible_supervisor' => null,
            'sampling_status'        => null,
            'sent_date'              => null,
            'is_archived'            => false,
        ]);

        $missing = SamplingMonitorTable::getMissingSendRequirements($wo);

        $this->assertContains('Supervisor Responsable', $missing);
        $this->assertContains('Estado de Muestreo', $missing);
        $this->assertCount(2, $missing);

        // Al intentar ejecutar el envío sin requisitos, no archiva
        $action = $this->getSendAction();
        $action->record($wo);
        $action->call();

        $this->assertFalse((bool) DrillHoleSample::where('work_order_id', $wo->id)->value('is_archived'));
    }

    public function test_detects_unvalidated_gaps_and_missing_core_size(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Guerrero', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna', 'code' => 'ML', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'DH-GAP-01', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 20]); // No core_size

        $wo = WorkOrder::create([
            'work_order_code' => 'WO-GAP-01',
            'sede_id'         => $sede->id,
        ]);

        // Muestra de 0 a 5m, dejando gap de 5m a 20m, y sin core_size
        DrillHoleSample::create([
            'user_id'                => $user->id,
            'work_order_id'          => $wo->id,
            'proyecto_id'            => $proyecto->id,
            'barreno_id'             => $barreno->id,
            'sample_number'          => 'SMP-GAP-1',
            'sample_type'            => 'Original',
            'from_depth'             => 0,
            'to_depth'               => 5,
            'responsible_supervisor' => 'MF',
            'sampling_status'        => 'Completed',
        ]);

        $missing = SamplingMonitorTable::getMissingSendRequirements($wo);

        $this->assertTrue(collect($missing)->contains(fn ($m) => str_contains($m, 'Core Size faltante')));
    }

    public function test_send_order_assigns_date_and_archives_when_valid(): void
    {
        $user = User::factory()->create();
        $sede = Sede::create(['name' => 'Sede Guerrero', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Media Luna', 'code' => 'ML', 'sede_id' => $sede->id]);
        $barreno = DrillHole::create(['nombre_barreno' => 'DH-102', 'proyecto_id' => $proyecto->id, 'start_depth' => 0, 'max_depth' => 10, 'core_size' => 'HQ']);

        $wo = WorkOrder::create([
            'work_order_code' => 'WO-VAL-02',
            'sede_id'         => $sede->id,
            'dispatch_date'   => null,
        ]);

        DrillHoleSample::create([
            'user_id'                => $user->id,
            'work_order_id'          => $wo->id,
            'proyecto_id'            => $proyecto->id,
            'barreno_id'             => $barreno->id,
            'sample_number'          => 'SMP-002',
            'sample_type'            => 'Original',
            'core_size'              => 'HQ',
            'from_depth'             => 0,
            'to_depth'               => 10,
            'responsible_supervisor' => 'MF',
            'sampling_status'        => 'Completed',
            'sent_date'              => null,
            'is_archived'            => false,
        ]);

        $action = $this->getSendAction();
        $action->record($wo);
        $action->data(['sent_date' => '2026-08-26']);
        $action->call(['sent_date' => '2026-08-26']);

        $sample = DrillHoleSample::where('work_order_id', $wo->id)->first();

        $this->assertTrue((bool) $sample->is_archived);
        $this->assertEquals('2026-08-26', $sample->sent_date->format('Y-m-d'));
        $this->assertEquals('2026-08-26', $wo->fresh()->dispatch_date->format('Y-m-d'));

        // Ahora devolver a Por Enviar
        $action->record($wo->fresh());
        $action->call();

        $this->assertFalse((bool) $sample->fresh()->is_archived);
    }
}
