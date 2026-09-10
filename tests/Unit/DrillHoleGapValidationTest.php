<?php

namespace Tests\Unit;

use App\Models\DrillHole;
use App\Models\DrillHoleGapValidation;
use App\Models\Proyecto;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrillHoleGapValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_selective_gap_validations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sede = Sede::create(['name' => 'Sede Test', 'city' => 'Cocula', 'state' => 'Guerrero', 'country' => 'Mexico']);
        $proyecto = Proyecto::create(['nombre' => 'Proyecto Test', 'code' => 'PRJ', 'sede_id' => $sede->id]);
        $drillHole = DrillHole::create([
            'nombre_barreno' => 'DH-VAL-01',
            'proyecto_id'    => $proyecto->id,
            'start_depth'    => 0,
            'max_depth'      => 100,
        ]);

        // Simular la acción de validación seleccionando solo 1 gap de 2
        $selectedGaps = [
            [
                'selected'   => true,
                'from_depth' => 0.00,
                'to_depth'   => 50.00,
                'reason'     => 'Core Loss',
                'notes'      => 'Nota de prueba',
            ],
            [
                'selected'   => false, // No seleccionado
                'from_depth' => 50.00,
                'to_depth'   => 100.00,
                'reason'     => 'Core Loss',
                'notes'      => null,
            ]
        ];

        $validToSave = array_filter($selectedGaps, fn ($g) => ! empty($g['selected']));

        foreach ($validToSave as $gap) {
            DrillHoleGapValidation::create([
                'barreno_id'   => $drillHole->id,
                'from_depth'   => $gap['from_depth'],
                'to_depth'     => $gap['to_depth'],
                'reason'       => $gap['reason'],
                'notes'        => $gap['notes'],
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);
        }

        $this->assertDatabaseHas('drill_hole_gap_validations', [
            'barreno_id' => $drillHole->id,
            'from_depth' => 0.00,
            'to_depth'   => 50.00,
            'reason'     => 'Core Loss',
        ]);

        $this->assertDatabaseMissing('drill_hole_gap_validations', [
            'barreno_id' => $drillHole->id,
            'from_depth' => 50.00,
            'to_depth'   => 100.00,
        ]);
    }
}
