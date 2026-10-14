<?php

namespace Tests\Feature\Api\Crm;

use App\Models\Academico\Curso;
use App\Models\Crm\Referido;
use App\Models\Crm\Seguimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas Feature de la bitácora de seguimientos del CRM.
 */
class SeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Referido $referido;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['crm_seguimientos', 'crm_seguimientoCrear', 'crm_seguimientoEditar', 'crm_seguimientoInactivar'] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['crm_seguimientos', 'crm_seguimientoCrear', 'crm_seguimientoEditar', 'crm_seguimientoInactivar']);

        $this->referido = Referido::factory()->create([
            'curso_id'  => Curso::factory()->create()->id,
            'gestor_id' => $this->usuario->id,
        ]);
    }

    /** @test */
    public function store_registra_seguimiento(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [
                'referido_id' => $this->referido->id,
                'seguidor_id' => $this->usuario->id,
                'fecha'       => now()->toDateString(),
                'seguimiento' => 'Llamada: interesado en jornada nocturna.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.seguimiento', 'Llamada: interesado en jornada nocturna.');
    }

    /** @test */
    public function store_valida_campos_obligatorios(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('seguimientos.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['referido_id', 'seguidor_id', 'fecha', 'seguimiento']);
    }

    /** @test */
    public function by_referido_lista_solo_la_bitacora_del_lead(): void
    {
        $otro = Referido::factory()->create(['curso_id' => $this->referido->curso_id, 'gestor_id' => $this->usuario->id]);
        Seguimiento::factory()->count(2)->create(['referido_id' => $this->referido->id, 'seguidor_id' => $this->usuario->id]);
        Seguimiento::factory()->create(['referido_id' => $otro->id, 'seguidor_id' => $this->usuario->id]);

        $this->actingAs($this->usuario)
            ->getJson(route('seguimientos.by-referido', $this->referido->id))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function usuario_sin_permiso_recibe_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('seguimientos.index'))
            ->assertForbidden();
    }
}
