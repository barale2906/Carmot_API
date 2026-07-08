<?php

namespace Tests\Feature\Api\Academico;

use App\Models\Academico\Aplazamiento;
use App\Models\Academico\AsistenciaClaseProgramada;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\Academico\TipoAplazamiento;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AplazamientoCicloTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private TipoAplazamiento $tipo;
    private Ciclo $ciclo;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_aplazamientos',        'descripcion' => 'ver aplazamientos']);
        Permission::create(['name' => 'aca_aplazamientoCrear',    'descripcion' => 'crear aplazamiento']);
        Permission::create(['name' => 'aca_aplazamientoInactivar','descripcion' => 'revertir aplazamiento']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_aplazamientos', 'aca_aplazamientoCrear', 'aca_aplazamientoInactivar']);

        $this->tipo = TipoAplazamiento::factory()->create(['nombre' => 'Prueba']);

        // Ciclo por iniciar, sin fecha_fin_automatica para simplicidad
        $this->ciclo = Ciclo::factory()->create([
            'fecha_inicio'        => now()->addDays(10)->format('Y-m-d'),
            'fecha_fin'           => now()->addDays(90)->format('Y-m-d'),
            'fecha_fin_automatica' => false,
            'status'              => 1,
        ]);
    }

    // =========================================================================
    // APLAZAR
    // =========================================================================

    /** @test */
    public function aplazar_mueve_fecha_inicio_del_ciclo(): void
    {
        $fechaReinicio = now()->addDays(25)->format('Y-m-d');

        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => $fechaReinicio,
            ]);

        $response->assertCreated();

        $this->ciclo->refresh();
        $this->assertEquals($fechaReinicio, $this->ciclo->fecha_inicio->format('Y-m-d'));
    }

    /** @test */
    public function aplazar_tambien_mueve_fecha_fin_cuando_es_manual(): void
    {
        $fechaFinOriginal = $this->ciclo->fecha_fin->copy();
        $fechaReinicio    = now()->addDays(25)->format('Y-m-d'); // 15 días después del inicio

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => $fechaReinicio,
            ]);

        $this->ciclo->refresh();

        $diasEsperados = 15;
        $this->assertEquals(
            $fechaFinOriginal->addDays($diasEsperados)->format('Y-m-d'),
            $this->ciclo->fecha_fin->format('Y-m-d')
        );
    }

    /** @test */
    public function aplazar_registra_aplazamiento_pendiente(): void
    {
        $fechaReinicio = now()->addDays(25)->format('Y-m-d');

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => $fechaReinicio,
            ]);

        $this->assertDatabaseHas('aplazamientos', [
            'ciclo_id'               => $this->ciclo->id,
            'tipo_aplazamiento_id'   => $this->tipo->id,
            'fecha_reinicio_probable' => $fechaReinicio,
            'estado'                 => 0, // Pendiente
        ]);
    }

    /** @test */
    public function aplazar_mueve_clases_programadas_y_deja_dictadas_intactas(): void
    {
        $claseProgramada = AsistenciaClaseProgramada::factory()->create([
            'ciclo_id'    => $this->ciclo->id,
            'grupo_id'    => \App\Models\Academico\Grupo::factory()->create()->id,
            'fecha_clase' => now()->addDays(20)->format('Y-m-d'),
            'estado'      => 'programada',
            'creado_por_id' => $this->usuario->id,
        ]);

        $claseDictada = AsistenciaClaseProgramada::factory()->create([
            'ciclo_id'    => $this->ciclo->id,
            'grupo_id'    => \App\Models\Academico\Grupo::factory()->create()->id,
            'fecha_clase' => now()->addDays(20)->format('Y-m-d'),
            'estado'      => 'dictada',
            'creado_por_id' => $this->usuario->id,
        ]);

        $fechaProgramadaOriginal = $claseProgramada->fecha_clase->copy();
        $fechaDictadaOriginal    = $claseDictada->fecha_clase->copy();

        // Aplazar 15 días (inicio + 10 → reinicio + 25 = +15 días)
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
            ]);

        $claseProgramada->refresh();
        $claseDictada->refresh();

        // Clase programada se movió 15 días
        $this->assertEquals(
            $fechaProgramadaOriginal->addDays(15)->format('Y-m-d'),
            $claseProgramada->fecha_clase->format('Y-m-d')
        );

        // Clase dictada NO se movió
        $this->assertEquals(
            $fechaDictadaOriginal->format('Y-m-d'),
            $claseDictada->fecha_clase->format('Y-m-d')
        );
    }

    /** @test */
    public function aplazar_mueve_cartera_y_agrega_nota_cuando_se_solicita(): void
    {
        [$matricula, $cartera] = $this->crearMatriculaConCartera();

        $fechaVencOriginal = $cartera->fecha_vencimiento->copy();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
                'mover_cartera'           => true,
            ]);

        $cartera->refresh();

        $this->assertEquals(
            $fechaVencOriginal->addDays(15)->format('Y-m-d'),
            $cartera->fecha_vencimiento->format('Y-m-d')
        );

        // Verifica que se agregó nota en observaciones
        $this->assertStringContainsString('[Aplazamiento]', $cartera->observaciones);
    }

    /** @test */
    public function aplazar_no_mueve_cartera_cerrada(): void
    {
        [$matricula, $cartera] = $this->crearMatriculaConCartera(status: Cartera::getStatusKey('Cerrada'));

        $fechaOriginal = $cartera->fecha_vencimiento->copy();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
                'mover_cartera'           => true,
            ]);

        $cartera->refresh();
        $this->assertEquals($fechaOriginal->format('Y-m-d'), $cartera->fecha_vencimiento->format('Y-m-d'));
    }

    /** @test */
    public function aplazar_no_mueve_cartera_cuando_no_se_solicita(): void
    {
        [$matricula, $cartera] = $this->crearMatriculaConCartera();
        $fechaOriginal = $cartera->fecha_vencimiento->copy();

        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
                'mover_cartera'           => false,
            ]);

        $cartera->refresh();
        $this->assertEquals($fechaOriginal->format('Y-m-d'), $cartera->fecha_vencimiento->format('Y-m-d'));
    }

    /** @test */
    public function aplazar_rechaza_ciclo_sin_fecha_inicio(): void
    {
        $cicloSinFecha = Ciclo::factory()->create([
            'fecha_inicio' => null,
            'status'       => 1,
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $cicloSinFecha), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(10)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ciclo');
    }

    /** @test */
    public function aplazar_rechaza_si_ya_hay_un_aplazamiento_pendiente(): void
    {
        // Primer aplazamiento
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
            ]);

        // Segundo intento sobre el mismo ciclo
        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(40)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ciclo');
    }

    /** @test */
    public function aplazar_rechaza_fecha_reinicio_no_posterior_al_inicio_del_ciclo(): void
    {
        $response = $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(5)->format('Y-m-d'), // antes del inicio
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('fecha_reinicio_probable');
    }

    // =========================================================================
    // CONFIRMAR
    // =========================================================================

    /** @test */
    public function confirmar_cierra_aplazamiento_sin_mover_fechas(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);
        $this->ciclo->refresh();
        $fechaInicioActual = $this->ciclo->fecha_inicio->copy();

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.confirmar', $aplazamiento));

        $response->assertOk();

        $aplazamiento->refresh();
        $this->assertEquals(1, $aplazamiento->estado); // Confirmado

        // Fecha del ciclo no se mueve
        $this->ciclo->refresh();
        $this->assertEquals($fechaInicioActual->format('Y-m-d'), $this->ciclo->fecha_inicio->format('Y-m-d'));
    }

    /** @test */
    public function confirmar_rechaza_aplazamiento_no_pendiente(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);
        $aplazamiento->update(['estado' => 1]); // ya confirmado

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.confirmar', $aplazamiento));

        $response->assertStatus(422);
    }

    // =========================================================================
    // AMPLIAR
    // =========================================================================

    /** @test */
    public function ampliar_crea_aplazamiento_hijo_y_marca_padre_como_ampliado(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);

        // La nueva fecha debe ser posterior a la fecha_reinicio_probable del padre (ciclo.fecha_inicio actual = +25 días)
        $nuevaFecha = now()->addDays(40)->format('Y-m-d');

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.ampliar', $aplazamiento), [
                'fecha_reinicio_probable' => $nuevaFecha,
            ]);

        $response->assertCreated();

        $aplazamiento->refresh();
        $this->assertEquals(2, $aplazamiento->estado); // Ampliado

        $hijo = Aplazamiento::where('aplazamiento_padre_id', $aplazamiento->id)->first();
        $this->assertNotNull($hijo);
        $this->assertEquals($nuevaFecha, $hijo->fecha_reinicio_probable->format('Y-m-d'));
        $this->assertEquals(0, $hijo->estado); // Pendiente
    }

    /** @test */
    public function ampliar_mueve_fechas_del_ciclo(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);
        $nuevaFecha   = now()->addDays(40)->format('Y-m-d');

        $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.ampliar', $aplazamiento), [
                'fecha_reinicio_probable' => $nuevaFecha,
            ]);

        $this->ciclo->refresh();
        $this->assertEquals($nuevaFecha, $this->ciclo->fecha_inicio->format('Y-m-d'));
    }

    /** @test */
    public function ampliar_rechaza_fecha_no_posterior_a_la_actual(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);

        // El ciclo ahora empieza en +25 días; nueva fecha en +20 días no es válida
        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.ampliar', $aplazamiento), [
                'fecha_reinicio_probable' => now()->addDays(20)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('fecha_reinicio_probable');
    }

    // =========================================================================
    // INTERRUMPIR
    // =========================================================================

    /** @test */
    public function interrumpir_ajusta_fechas_hacia_atras(): void
    {
        $aplazamiento   = $this->crearAplazamientoPendiente(diasReinicio: 25);
        $fechaFinActual = $this->ciclo->refresh()->fecha_fin->copy();

        // Ciclo reinicia en +20 días (5 días antes de la fecha probable +25)
        $fechaReal = now()->addDays(20)->format('Y-m-d');

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.interrumpir', $aplazamiento), [
                'fecha_reinicio_real' => $fechaReal,
            ]);

        $response->assertOk();

        $aplazamiento->refresh();
        $this->assertEquals(4, $aplazamiento->estado); // Interrumpido
        $this->assertEquals($fechaReal, $aplazamiento->fecha_reinicio_real->format('Y-m-d'));
        $this->assertEquals(10, $aplazamiento->dias_reales); // diferencia entre original (+10) y real (+20)

        // ciclo.fecha_inicio ahora es +20 días (5 días menos que la probable +25)
        $this->ciclo->refresh();
        $this->assertEquals($fechaReal, $this->ciclo->fecha_inicio->format('Y-m-d'));

        // fecha_fin también se redujo 5 días
        $this->assertEquals(
            $fechaFinActual->subDays(5)->format('Y-m-d'),
            $this->ciclo->fecha_fin->format('Y-m-d')
        );
    }

    /** @test */
    public function interrumpir_rechaza_fecha_posterior_a_la_probable(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.interrumpir', $aplazamiento), [
                'fecha_reinicio_real' => now()->addDays(30)->format('Y-m-d'), // después de probable +25
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('fecha_reinicio_real');
    }

    /** @test */
    public function interrumpir_rechaza_fecha_anterior_o_igual_al_inicio_original(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.interrumpir', $aplazamiento), [
                'fecha_reinicio_real' => now()->addDays(10)->format('Y-m-d'), // igual al inicio_original
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('fecha_reinicio_real');
    }

    // =========================================================================
    // REVERTIR
    // =========================================================================

    /** @test */
    public function revertir_restaura_fecha_inicio_original(): void
    {
        $fechaInicioOriginal = $this->ciclo->fecha_inicio->copy();
        $aplazamiento        = $this->crearAplazamientoPendiente(diasReinicio: 25);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.revertir', $aplazamiento));

        $response->assertOk();

        $aplazamiento->refresh();
        $this->assertEquals(3, $aplazamiento->estado); // Revertido
        $this->assertEquals(0, $aplazamiento->dias_reales);

        $this->ciclo->refresh();
        $this->assertEquals($fechaInicioOriginal->format('Y-m-d'), $this->ciclo->fecha_inicio->format('Y-m-d'));
    }

    /** @test */
    public function revertir_restaura_cartera_con_nota(): void
    {
        [$matricula, $cartera] = $this->crearMatriculaConCartera();
        $fechaVencOriginal     = $cartera->fecha_vencimiento->copy();

        // Aplazar con cartera
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25, moverCartera: true);
        $cartera->refresh();
        $fechaVencMovida = $cartera->fecha_vencimiento->copy();

        // La fecha debió haberse movido
        $this->assertNotEquals($fechaVencOriginal->format('Y-m-d'), $fechaVencMovida->format('Y-m-d'));

        // Revertir
        $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.revertir', $aplazamiento));

        $cartera->refresh();

        $this->assertEquals($fechaVencOriginal->format('Y-m-d'), $cartera->fecha_vencimiento->format('Y-m-d'));
        $this->assertStringContainsString('[Reversión aplaz.', $cartera->observaciones);
    }

    /** @test */
    public function revertir_rechaza_aplazamiento_no_pendiente(): void
    {
        $aplazamiento = $this->crearAplazamientoPendiente(diasReinicio: 25);
        $aplazamiento->update(['estado' => 1]); // Confirmado

        $response = $this->actingAs($this->usuario)
            ->postJson(route('aplazamientos.revertir', $aplazamiento));

        $response->assertStatus(422);
    }

    // =========================================================================
    // PERMISOS
    // =========================================================================

    /** @test */
    public function permiso_denegado_sin_permiso_aplazar(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays(25)->format('Y-m-d'),
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Crea un aplazamiento pendiente sobre $this->ciclo llamando al endpoint.
     */
    private function crearAplazamientoPendiente(int $diasReinicio, bool $moverCartera = false): Aplazamiento
    {
        $this->actingAs($this->usuario)
            ->postJson(route('ciclos.aplazar', $this->ciclo), [
                'tipo_aplazamiento_id'   => $this->tipo->id,
                'fecha_reinicio_probable' => now()->addDays($diasReinicio)->format('Y-m-d'),
                'mover_cartera'           => $moverCartera,
            ]);

        return Aplazamiento::where('ciclo_id', $this->ciclo->id)->latest()->first();
    }

    /**
     * Crea una matrícula y una cartera activa vinculada al ciclo de prueba.
     *
     * @return array{0: Matricula, 1: Cartera}
     */
    private function crearMatriculaConCartera(int $status = null): array
    {
        $status     = $status ?? Cartera::getStatusKey('Activa');
        $estudiante = User::factory()->create();
        $sede       = Sede::factory()->create();

        $matricula = Matricula::factory()->create([
            'ciclo_id'           => $this->ciclo->id,
            'estudiante_id'      => $estudiante->id,
            'sede_id'            => $sede->id,
            'status'             => 0, // Inactiva para no disparar contadores
        ]);

        $cartera = Cartera::factory()->create([
            'matricula_id'      => $matricula->id,
            'sede_id'           => $sede->id,
            'estudiante_id'     => $estudiante->id,
            'fecha_vencimiento' => now()->addDays(60)->format('Y-m-d'),
            'status'            => $status,
            'observaciones'     => null,
        ]);

        return [$matricula, $cartera];
    }
}
