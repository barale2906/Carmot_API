<?php

namespace Tests\Feature\Api\Dashboard;

use App\Models\Dashboard\Dashboard;
use App\Models\Dashboard\DashboardCard;
use App\Models\Dashboard\Kpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de creación y edición de tarjetas de dashboard. Cubre el bug B39:
 * la tabla no tenía las columnas de gráfico y crear una tarjeta respondía 500.
 */
class DashboardCardTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Dashboard $dashboard;
    private Kpi $kpi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->dashboard = Dashboard::create(['user_id' => $this->usuario->id, 'name' => 'Tablero de prueba']);
        $this->kpi = Kpi::create([
            'name' => 'Grupos activos',
            'code' => 'GRUPOS_ACTIVOS_TEST',
            'is_active' => true,
            'numerator_model' => 1,
            'numerator_field' => 'id',
            'numerator_operation' => 'count',
            'date_field' => 'created_at',
        ]);
    }

    /** @test */
    public function crea_una_tarjeta_con_tipo_parametros_agrupacion_y_filtros_de_grafico(): void
    {
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/dashboard/dashboard-cards', [
                'dashboard_id' => $this->dashboard->id,
                'kpi_id' => $this->kpi->id,
                'title' => 'Grupos por sede',
                'chart_type' => 'bar',
                'chart_parameters' => ['orientation' => 'horizontal', 'show_values' => true],
                'group_by' => 'sede_id',
                'filters' => [['field' => 'status', 'type' => 'exact', 'value' => 1]],
            ])
            ->assertCreated();

        $card = DashboardCard::firstOrFail();
        $this->assertSame('bar', $card->chart_type);
        $this->assertSame('horizontal', $card->chart_parameters['orientation']);
        $this->assertSame('sede_id', $card->group_by);
        $this->assertSame('status', $card->filters[0]['field']);
    }

    /** @test */
    public function actualiza_el_tipo_de_grafico_de_una_tarjeta(): void
    {
        $card = DashboardCard::create([
            'dashboard_id' => $this->dashboard->id,
            'kpi_id' => $this->kpi->id,
            'chart_type' => 'bar',
        ]);

        $this->actingAs($this->usuario, 'sanctum')
            ->putJson("/api/dashboard/dashboard-cards/{$card->id}", ['chart_type' => 'pie'])
            ->assertOk();

        $this->assertSame('pie', $card->fresh()->chart_type);
    }

    /** @test */
    public function rechaza_un_tipo_de_grafico_no_soportado(): void
    {
        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/dashboard/dashboard-cards', [
                'dashboard_id' => $this->dashboard->id,
                'kpi_id' => $this->kpi->id,
                'chart_type' => 'radar',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chart_type');
    }

    /** @test */
    public function otro_usuario_no_modifica_las_tarjetas_de_un_tablero_ajeno(): void
    {
        $card = DashboardCard::create(['dashboard_id' => $this->dashboard->id, 'kpi_id' => $this->kpi->id]);
        $otro = User::factory()->create();

        $this->actingAs($otro, 'sanctum')
            ->postJson('/api/dashboard/dashboard-cards', ['dashboard_id' => $this->dashboard->id, 'kpi_id' => $this->kpi->id])
            ->assertForbidden();

        $this->actingAs($otro, 'sanctum')
            ->putJson("/api/dashboard/dashboard-cards/{$card->id}", ['chart_type' => 'pie'])
            ->assertForbidden();

        $this->actingAs($otro, 'sanctum')
            ->deleteJson("/api/dashboard/dashboard-cards/{$card->id}")
            ->assertForbidden();

        $this->actingAs($otro, 'sanctum')
            ->deleteJson("/api/dashboard/dashboards/{$this->dashboard->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('dashboard_cards', ['id' => $card->id, 'deleted_at' => null]);
    }
}
