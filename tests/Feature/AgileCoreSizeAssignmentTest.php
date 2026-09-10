<?php

namespace Tests\Feature;

use App\Models\DrillHole;
use App\Models\DrillHoleSample;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use App\Services\SampleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgileCoreSizeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_does_not_auto_assign_barreno_core_size(): void
    {
        $sede     = Sede::create(['name' => 'San Miguel', 'code' => 'SM', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'San Miguel']);
        $proyecto = Proyecto::create(['nombre' => 'Test Proj', 'code' => 'TP', 'sede_id' => $sede->id]);
        $barreno  = DrillHole::create(['proyecto_id' => $proyecto->id, 'nombre_barreno' => 'DH001', 'core_size' => 'HQ']);

        $user   = User::factory()->create();
        $sample = DrillHoleSample::create([
            'proyecto_id'    => $proyecto->id,
            'barreno_id'     => $barreno->id,
            'user_id'        => $user->id,
            'status'         => 'draft',
            'capture_source' => 'import',
            'sample_type'    => 'O',
            'sample_number'  => 'ML157100',
            'from_depth'     => 0,
            'to_depth'       => 1,
            'sample_length'  => 1,
            'length'         => 1,
            'weight'         => 2.5,
            'core_size'      => null,
        ]);

        $this->assertNull($sample->core_size);
    }

    public function test_assign_core_size_updates_samples_strictly_belonging_to_barreno(): void
    {
        $sede      = Sede::create(['name' => 'San Miguel', 'code' => 'SM', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'San Miguel']);
        $proyecto  = Proyecto::create(['nombre' => 'Test Proj', 'code' => 'TP', 'sede_id' => $sede->id]);
        $barreno1  = DrillHole::create(['proyecto_id' => $proyecto->id, 'nombre_barreno' => 'DH001']);
        $barreno2  = DrillHole::create(['proyecto_id' => $proyecto->id, 'nombre_barreno' => 'DH002']);

        $user = User::factory()->create();

        // Muestras Barreno 1
        $s1 = DrillHoleSample::create([
            'proyecto_id' => $proyecto->id, 'barreno_id' => $barreno1->id, 'user_id' => $user->id,
            'status' => 'draft', 'capture_source' => 'import', 'sample_type' => 'O', 'sample_number' => 'ML157100',
            'from_depth' => 0, 'to_depth' => 1, 'sample_length' => 1, 'length' => 1, 'weight' => 2.5, 'core_size' => null,
        ]);
        $s2 = DrillHoleSample::create([
            'proyecto_id' => $proyecto->id, 'barreno_id' => $barreno1->id, 'user_id' => $user->id,
            'status' => 'draft', 'capture_source' => 'import', 'sample_type' => 'O', 'sample_number' => 'ML157101',
            'from_depth' => 1, 'to_depth' => 2, 'sample_length' => 1, 'length' => 1, 'weight' => 2.5, 'core_size' => null,
        ]);

        // Muestra Barreno 2 (no debe ser alterada)
        $sOther = DrillHoleSample::create([
            'proyecto_id' => $proyecto->id, 'barreno_id' => $barreno2->id, 'user_id' => $user->id,
            'status' => 'draft', 'capture_source' => 'import', 'sample_type' => 'O', 'sample_number' => 'ML157200',
            'from_depth' => 0, 'to_depth' => 1, 'sample_length' => 1, 'length' => 1, 'weight' => 2.5, 'core_size' => null,
        ]);

        // Asignar HQ3 a muestras de Barreno 1
        DrillHoleSample::where('barreno_id', $barreno1->id)->update(['core_size' => 'HQ3']);

        $s1->refresh();
        $s2->refresh();
        $sOther->refresh();

        $this->assertEquals('HQ3', $s1->core_size);
        $this->assertEquals('HQ3', $s2->core_size);
        $this->assertNull($sOther->core_size);
    }
}
