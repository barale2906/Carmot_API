<?php

namespace Tests\Feature\Api\Configuracion;

use App\Models\Configuracion\Horario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Las tarjetas de Horarios cuadran: total = activos + eliminados (F17).
 */
class HorarioEstadisticasTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function el_total_incluye_activos_y_eliminados(): void
    {
        Permission::create(['name' => 'co_horarios', 'descripcion' => 'ver horarios']);
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('co_horarios');

        Horario::factory()->count(3)->create();
        Horario::factory()->create()->delete();

        $totales = $this->actingAs($usuario)->getJson(route('horarios.statistics'))
            ->assertOk()
            ->json('data.totales');

        // La factory crea sedes que generan sus propios horarios: se valida la relación
        $this->assertSame(Horario::count(), $totales['activos']);
        $this->assertSame(1, $totales['eliminados']);
        $this->assertSame($totales['activos'] + $totales['eliminados'], $totales['total']);
    }
}
