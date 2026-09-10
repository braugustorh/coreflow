<?php

namespace Tests\Feature;

use App\Filament\Resources\DrillHoleSampleResource\Pages\ListDraftDrillHoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DraftDrillHolesLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_can_render_successfully(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        Livewire::test(ListDraftDrillHoles::class)
            ->assertSuccessful();
    }

    public function test_review_page_can_render_successfully(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $sede     = \App\Models\Sede::create(['name' => 'Media Luna', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'Cocula']);
        $proyecto = \App\Models\Proyecto::create(['nombre' => 'Morelos', 'code' => 'MOR', 'sede_id' => $sede->id]);
        $barreno  = \App\Models\DrillHole::create(['nombre_barreno' => 'ML-001', 'sede_id' => $sede->id, 'proyecto_id' => $proyecto->id]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->assertSuccessful();
    }

    public function test_review_page_displays_controls_and_sample_types_correctly(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $sede     = \App\Models\Sede::create(['name' => 'Media Luna', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'Cocula']);
        $proyecto = \App\Models\Proyecto::create(['nombre' => 'Morelos', 'code' => 'MOR', 'sede_id' => $sede->id]);
        $barreno  = \App\Models\DrillHole::create(['nombre_barreno' => 'ML-100', 'sede_id' => $sede->id, 'proyecto_id' => $proyecto->id]);
        $standard = \App\Models\StandardSample::create(['standard_name' => 'OREAS-254']);

        // 1. Muestra Original
        $orig = \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML100001',
            'sample_type' => 'O',
            'status' => 'draft',
            'capture_source' => 'import',
            'from_depth' => 0,
            'to_depth' => 1.5,
            'length' => 1.5,
        ]);

        // 2. Muestra Blank
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML100002',
            'sample_type' => 'Control',
            'control_type' => 'BLANK',
            'status' => 'draft',
            'capture_source' => 'import',
        ]);

        // 3. Muestra Standard
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML100003',
            'sample_type' => 'Control',
            'control_type' => 'Standard',
            'standard_sample_id' => $standard->id,
            'status' => 'draft',
            'capture_source' => 'import',
        ]);

        // 4. Muestra Duplicado
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML100004',
            'sample_type' => 'Control',
            'control_type' => 'Duplicate',
            'duplicate_sample_id' => $orig->id,
            'status' => 'draft',
            'capture_source' => 'import',
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->call('loadTable')
            ->assertSuccessful()
            ->assertSee('Blank-ML')
            ->assertSee('OREAS-254')
            ->assertSee('DUP-ML100001');
    }

    public function test_review_page_filters_work_correctly(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $sede     = \App\Models\Sede::create(['name' => 'Media Luna', 'country' => 'Mexico', 'state' => 'Guerrero', 'city' => 'Cocula']);
        $proyecto = \App\Models\Proyecto::create(['nombre' => 'Morelos', 'code' => 'MOR', 'sede_id' => $sede->id]);
        $barreno  = \App\Models\DrillHole::create(['nombre_barreno' => 'ML-200', 'sede_id' => $sede->id, 'proyecto_id' => $proyecto->id]);
        $standard = \App\Models\StandardSample::create(['standard_name' => 'OREAS-501']);

        // 1. Muestra Original sin error
        $orig = \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML200001',
            'sample_type' => 'O',
            'status' => 'draft',
            'capture_source' => 'import',
            'from_depth' => 0,
            'to_depth' => 1.5,
            'length' => 1.5,
            'errors' => null,
        ]);

        // 2. Muestra con GAP
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML200002',
            'sample_type' => 'O',
            'status' => 'draft',
            'capture_source' => 'import',
            'from_depth' => 3.0,
            'to_depth' => 4.5,
            'length' => 1.5,
            'errors' => ['GAP de 1.5 m sin validar (de 1.5m a 3m)'],
        ]);

        // 3. Muestra Blank
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML200003',
            'sample_type' => 'Control',
            'control_type' => 'Blank-ML',
            'status' => 'draft',
            'capture_source' => 'import',
            'errors' => null,
        ]);

        // 4. Muestra Standard
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML200004',
            'sample_type' => 'Control',
            'control_type' => 'Standard',
            'standard_sample_id' => $standard->id,
            'status' => 'draft',
            'capture_source' => 'import',
            'errors' => null,
        ]);

        // 5. Muestra con otro error (por ejemplo peso)
        \App\Models\DrillHoleSample::create([
            'user_id' => $user->id,
            'barreno_id' => $barreno->id,
            'proyecto_id' => $proyecto->id,
            'sample_number' => 'ML200005',
            'sample_type' => 'O',
            'status' => 'draft',
            'capture_source' => 'import',
            'from_depth' => 4.5,
            'to_depth' => 6.0,
            'length' => 1.5,
            'errors' => ['El peso (0.1 kg) es inferior al mínimo permitido.'],
        ]);

        $this->actingAs($user);

        // Test filtrar por Blancos
        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->filterTable('tipo_registro', 'blanks')
            ->call('loadTable')
            ->assertSee('ML200003')
            ->assertDontSee('ML200001')
            ->assertDontSee('ML200004');

        // Test filtrar por Estandards
        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->filterTable('tipo_registro', 'standards')
            ->call('loadTable')
            ->assertSee('ML200004')
            ->assertDontSee('ML200001')
            ->assertDontSee('ML200003');

        // Test filtrar por Gaps
        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->filterTable('tipo_registro', 'gaps')
            ->call('loadTable')
            ->assertSee('ML200002')
            ->assertDontSee('ML200001')
            ->assertDontSee('ML200003');

        // Test filtrar por Registro con errores
        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->filterTable('tipo_registro', 'errors')
            ->call('loadTable')
            ->assertSee('ML200002')
            ->assertSee('ML200005')
            ->assertDontSee('ML200001')
            ->assertDontSee('ML200003');

        // Test filtrar por Registros de Originales
        Livewire::test(\App\Filament\Resources\DrillHoleSampleResource\Pages\ReviewDrillHoleSamples::class, ['record' => $barreno->id])
            ->filterTable('tipo_registro', 'originals')
            ->call('loadTable')
            ->assertSee('ML200001')
            ->assertSee('ML200002')
            ->assertSee('ML200005')
            ->assertDontSee('ML200003')
            ->assertDontSee('ML200004');
    }
}
