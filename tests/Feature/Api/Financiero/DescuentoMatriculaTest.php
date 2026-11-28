<?php

namespace Tests\Feature\Api\Financiero;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Academico\Programacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\User;
use App\Services\Financiero\MatriculaDescuentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Descuentos de matrícula por sede, curso y jornada: alcance, acumulación,
 * vista previa, congelado al matricular y recálculo al editar.
 */
class DescuentoMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Curso $curso;
    private Ciclo $ciclo;
    private Programacion $programacion;
    private LpPrecioProducto $lp;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'aca_matriculaCrear', 'descripcion' => 'crear matricula']);
        Permission::create(['name' => 'aca_matriculaEditar', 'descripcion' => 'editar matricula']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo(['aca_matriculaCrear', 'aca_matriculaEditar']);

        $this->sede  = Sede::factory()->create();
        $this->curso = Curso::factory()->create();
        $this->ciclo = Ciclo::factory()->create(['sede_id' => $this->sede->id, 'curso_id' => $this->curso->id]);
        $this->programacion = Programacion::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => $this->curso->id, 'jornada' => 2,
        ]);
        // Curso de 1.000.000: matrícula 200.000 + 800.000 en 4 cuotas
        $this->lp = LpPrecioProducto::factory()->financiable()->conValores(1000000, 800000, 200000, 4)->create();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function descuento(array $attrs = []): Descuento
    {
        return Descuento::factory()->vigente()->promocionMatricula()->create(array_merge([
            'tipo'                => Descuento::TIPO_PORCENTUAL,
            'valor'               => 10,
            'aplicacion'          => Descuento::APLICACION_MATRICULA,
            'permite_acumulacion' => false,
        ], $attrs));
    }

    private function contexto(array $overrides = []): array
    {
        return array_merge([
            'sede_id'               => $this->sede->id,
            'curso_id'              => $this->curso->id,
            'ciclo_id'              => $this->ciclo->id,
            'programacion_id'       => $this->programacion->id,
            'lp_precio_producto_id' => $this->lp->id,
        ], $overrides);
    }

    private function datosMatricula(array $overrides = []): array
    {
        return array_merge($this->contexto(), [
            'estudiante_id'      => User::factory()->create()->id,
            'matriculado_por_id' => $this->usuario->id,
            'comercial_id'       => $this->usuario->id,
            'fecha_matricula'    => now()->toDateString(),
            'fecha_inicio'       => now()->addDay()->toDateString(),
            'monto'              => 1000000,
        ], $overrides);
    }

    private function resolver(?int $jornada = 2, ?int $sedeId = null, ?int $cursoId = null)
    {
        return app(MatriculaDescuentoService::class)->resolver(
            $sedeId ?? $this->sede->id,
            $cursoId ?? $this->curso->id,
            $jornada,
            200000.0
        );
    }

    // ─── Alcance (sede, curso, jornada) ───────────────────────────────────────

    /** @test */
    public function descuento_sin_restricciones_aplica_a_cualquier_sede_curso_y_jornada(): void
    {
        $d = $this->descuento();

        $this->assertTrue($this->resolver(2)->contains('id', $d->id));
        $this->assertTrue($this->resolver(null)->contains('id', $d->id));
        $this->assertTrue($this->resolver(0, Sede::factory()->create()->id, Curso::factory()->create()->id)->contains('id', $d->id));
    }

    /** @test */
    public function descuento_por_sede_solo_aplica_en_esa_sede(): void
    {
        $d = $this->descuento();
        $d->sedes()->sync([$this->sede->id]);

        $this->assertTrue($this->resolver()->contains('id', $d->id));
        $this->assertFalse($this->resolver(2, Sede::factory()->create()->id)->contains('id', $d->id));
    }

    /** @test */
    public function descuento_por_curso_solo_aplica_a_ese_curso(): void
    {
        $d = $this->descuento();
        $d->cursos()->sync([$this->curso->id]);

        $this->assertTrue($this->resolver()->contains('id', $d->id));
        $this->assertFalse($this->resolver(2, null, Curso::factory()->create()->id)->contains('id', $d->id));
    }

    /** @test */
    public function descuento_por_jornada_solo_aplica_a_esa_jornada_y_no_sin_programacion(): void
    {
        $d = $this->descuento(['jornadas' => [2, 3]]);

        $this->assertTrue($this->resolver(2)->contains('id', $d->id));
        $this->assertFalse($this->resolver(0)->contains('id', $d->id));
        $this->assertFalse($this->resolver(null)->contains('id', $d->id));
    }

    /** @test */
    public function sede_curso_y_jornada_se_combinan_con_and(): void
    {
        $d = $this->descuento(['jornadas' => [2]]);
        $d->sedes()->sync([$this->sede->id]);
        $d->cursos()->sync([$this->curso->id]);

        $this->assertTrue($this->resolver(2)->contains('id', $d->id));
        $this->assertFalse($this->resolver(1)->contains('id', $d->id), 'jornada distinta');
        $this->assertFalse($this->resolver(2, null, Curso::factory()->create()->id)->contains('id', $d->id), 'curso distinto');
        $this->assertFalse($this->resolver(2, Sede::factory()->create()->id)->contains('id', $d->id), 'sede distinta');
    }

    /** @test */
    public function ignora_descuentos_no_vigentes_de_inventario_o_de_otra_activacion(): void
    {
        $this->descuento(['status' => Descuento::STATUS_INACTIVO]);
        $this->descuento(['origen' => 0]);
        Descuento::factory()->vigente()->pagoAnticipado()->create(['aplicacion' => Descuento::APLICACION_CUOTA]);

        $this->assertCount(0, $this->resolver());
    }

    // ─── Acumulación ──────────────────────────────────────────────────────────

    /** @test */
    public function descuentos_acumulables_se_aplican_en_secuencia(): void
    {
        $this->descuento(['permite_acumulacion' => true]);
        $this->descuento(['permite_acumulacion' => true]);

        $resuelto = $this->resolver();
        $this->assertCount(2, $resuelto);

        // 10 % de 200.000 = 20.000; luego 10 % de 180.000 = 18.000
        $this->assertEquals(38000, app(MatriculaDescuentoService::class)->aplicarSecuencial($resuelto, 200000.0)['total']);
    }

    /** @test */
    public function descuento_no_acumulable_gana_solo_si_supera_a_los_acumulables_juntos(): void
    {
        $a = $this->descuento(['permite_acumulacion' => true, 'valor' => 10]);
        $b = $this->descuento(['permite_acumulacion' => true, 'valor' => 10]);
        $exclusivo = $this->descuento(['permite_acumulacion' => false, 'valor' => 30]);

        // 30 % (60.000) > 38.000 de los acumulables: gana el exclusivo y va solo
        $this->assertEquals([$exclusivo->id], $this->resolver()->pluck('id')->all());

        // Con un exclusivo de 15 % (30.000 < 38.000) ganan los acumulables
        $exclusivo->update(['valor' => 15]);
        $this->assertEquals([$a->id, $b->id], $this->resolver()->pluck('id')->all());
    }

    /** @test */
    public function entre_varios_no_acumulables_aplica_el_de_mayor_valor(): void
    {
        $this->descuento(['valor' => 5]);
        $mayor = $this->descuento(['valor' => 20]);

        $this->assertEquals([$mayor->id], $this->resolver()->pluck('id')->all());
    }

    // ─── Vista previa ─────────────────────────────────────────────────────────

    /** @test */
    public function vista_previa_muestra_total_del_curso_y_detalle_del_descuento(): void
    {
        $d = $this->descuento(['valor' => 25, 'nombre' => 'Promo noche']);
        $d->jornadas = [2];
        $d->save();
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'aplicacion' => Descuento::APLICACION_CUOTA, 'dias_anticipacion' => 5,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.precalcular-descuentos'), $this->contexto())
            ->assertOk()
            ->assertJsonPath('data.total_curso', 1000000)
            ->assertJsonPath('data.valor_matricula', 200000)
            ->assertJsonPath('data.jornada', 2)
            ->assertJsonPath('data.descuentos.0.id', $d->id)
            ->assertJsonPath('data.descuentos.0.nombre', 'Promo noche')
            ->assertJsonPath('data.descuentos.0.valor_descuento', 50000)
            ->assertJsonPath('data.total_descuento', 50000)
            ->assertJsonPath('data.valor_matricula_con_descuento', 150000)
            ->assertJsonPath('data.total_curso_con_descuento', 950000)
            ->assertJsonCount(1, 'data.descuentos_pago_anticipado');
    }

    /** @test */
    public function vista_previa_sin_descuento_aplicable_devuelve_lista_vacia(): void
    {
        $this->descuento(['jornadas' => [0]]); // la programación es jornada 2

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.precalcular-descuentos'), $this->contexto())
            ->assertOk()
            ->assertJsonPath('data.descuentos', [])
            ->assertJsonPath('data.total_descuento', 0)
            ->assertJsonPath('data.total_curso_con_descuento', 1000000);
    }

    /** @test */
    public function vista_previa_valida_datos_y_programacion_ajena(): void
    {
        $otraProgramacion = Programacion::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => Curso::factory()->create()->id,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.precalcular-descuentos'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sede_id', 'curso_id', 'ciclo_id']);

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.precalcular-descuentos'), $this->contexto(['programacion_id' => $otraProgramacion->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('programacion_id');
    }

    /** @test */
    public function vista_previa_rechaza_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('matriculas.precalcular-descuentos'), $this->contexto())
            ->assertForbidden();
    }

    /** @test */
    public function el_pronto_pago_solo_aplica_si_su_alcance_cubre_la_matricula(): void
    {
        $matricula = Matricula::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => $this->curso->id,
            'ciclo_id' => $this->ciclo->id, 'programacion_id' => $this->programacion->id,
            'lp_precio_producto_id' => null,
        ]);
        $propio = Descuento::factory()->vigente()->pagoAnticipado()->create(['aplicacion' => Descuento::APLICACION_CUOTA]);
        $propio->jornadas = [2];
        $propio->save();
        $ajeno = Descuento::factory()->vigente()->pagoAnticipado()->create(['aplicacion' => Descuento::APLICACION_CUOTA]);
        $ajeno->sedes()->sync([Sede::factory()->create()->id]);

        $servicio = app(\App\Services\Financiero\CarteraDescuentoService::class);

        $this->assertEquals($propio->id, $servicio->obtenerDescuentoProntoPago($matricula)?->id);

        $propio->update(['jornadas' => [0]]);
        $this->assertNull($servicio->obtenerDescuentoProntoPago($matricula));
    }

    // ─── Congelado al matricular ──────────────────────────────────────────────

    /** @test */
    public function al_matricular_se_congelan_los_descuentos_que_aplican(): void
    {
        $aplica = $this->descuento(['valor' => 20]);
        $this->descuento(['jornadas' => [0]]); // otra jornada: no aplica

        $response = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosMatricula())
            ->assertCreated()
            ->assertJsonPath('data.programacion_id', $this->programacion->id)
            ->assertJsonCount(1, 'data.descuentos_congelados')
            ->assertJsonPath('data.descuentos_congelados.0.id', $aplica->id)
            ->assertJsonPath('data.descuentos_congelados.0.valor', 20);

        $this->assertDatabaseHas('matricula_descuento', [
            'matricula_id' => $response->json('data.id'),
            'descuento_id' => $aplica->id,
            'tipo'         => 'porcentual',
        ]);
    }

    /** @test */
    public function el_descuento_congelado_no_cambia_si_se_edita_la_promocion(): void
    {
        $d = $this->descuento(['valor' => 20]);

        $id = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosMatricula())
            ->json('data.id');

        $d->update(['valor' => 90]);

        $info = app(\App\Services\Financiero\CarteraDescuentoService::class)
            ->calcularDescuentoMatricula(Matricula::findOrFail($id));

        // 20 % de la matrícula de 200.000, no el 90 % nuevo
        $this->assertTrue($info['aplica']);
        $this->assertEquals(40000, $info['valor']);
    }

    /** @test */
    public function matricula_sin_descuentos_aplicables_no_congela_nada(): void
    {
        $this->descuento(['jornadas' => [0]]);

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosMatricula())
            ->assertCreated()
            ->assertJsonCount(0, 'data.descuentos_congelados');

        $this->assertDatabaseCount('matricula_descuento', 0);
    }

    /** @test */
    public function rechaza_matricular_con_programacion_de_otro_curso(): void
    {
        $otra = Programacion::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => Curso::factory()->create()->id,
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosMatricula(['programacion_id' => $otra->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('programacion_id');
    }

    // ─── Traslado de programación (jornada) ───────────────────────────────────

    /**
     * Matricula al estudiante y devuelve el cuerpo de un traslado a la programación indicada (mismo
     * ciclo, sede y curso), para elegir si se recalculan los descuentos.
     */
    private function trasladoDeProgramacion(Programacion $destino, array $extra = []): array
    {
        Permission::create(['name' => 'tra_trasladoCrear', 'descripcion' => 'registrar traslados']);
        $this->usuario->givePermissionTo('tra_trasladoCrear');
        $this->usuario->sedes()->attach($this->sede->id);
        // El ciclo del factory es aleatorio: el traslado necesita un ciclo activo y vigente
        $this->ciclo->update(['status' => 1, 'fecha_fin' => now()->addYear()->toDateString()]);

        $id = $this->actingAs($this->usuario)
            ->postJson(route('matriculas.store'), $this->datosMatricula())
            ->json('data.id');

        return array_merge(
            $this->contexto(['programacion_id' => $destino->id, 'matricula_id' => $id, 'motivo' => 'Cambio de jornada']),
            ['lp_precio_producto_id' => null],
            $extra
        );
    }

    /** @test */
    public function al_trasladar_de_programacion_el_usuario_elige_si_recalcula_los_descuentos(): void
    {
        $noche = $this->descuento(['jornadas' => [2]]);
        $dia   = $this->descuento(['jornadas' => [0]]);
        $programacionDia = Programacion::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => $this->curso->id, 'jornada' => 0, 'status' => 1,
        ]);

        // Sin recalcular: se conserva el descuento del momento de matricular
        $datos = $this->trasladoDeProgramacion($programacionDia);
        $this->actingAs($this->usuario)->postJson(route('tra-traslados.store'), $datos)->assertCreated();
        $this->assertSame([$noche->id], Matricula::findOrFail($datos['matricula_id'])->descuentosCongelados->pluck('id')->all());

        // Recalculando: pasa al descuento de la nueva jornada
        // (vuelve primero a la programación de noche y luego se traslada de nuevo a la de día)
        $this->actingAs($this->usuario)
            ->postJson(route('tra-traslados.store'), array_merge($datos, ['programacion_id' => $this->programacion->id]))
            ->assertCreated();
        $this->actingAs($this->usuario)
            ->postJson(route('tra-traslados.store'), array_merge($datos, ['programacion_id' => $programacionDia->id, 'recalcular_descuentos' => true]))
            ->assertCreated();
        $this->assertSame([$dia->id], Matricula::findOrFail($datos['matricula_id'])->descuentosCongelados->pluck('id')->all());
    }

    /** @test */
    public function no_permite_recalcular_al_trasladar_si_la_matricula_ya_recibio_descuento_en_su_pago(): void
    {
        $this->descuento();
        $programacionDia = Programacion::factory()->create([
            'sede_id' => $this->sede->id, 'curso_id' => $this->curso->id, 'jornada' => 0, 'status' => 1,
        ]);

        $datos = $this->trasladoDeProgramacion($programacionDia, ['recalcular_descuentos' => true]);
        Matricula::findOrFail($datos['matricula_id'])->carteras()->where('numero_cuota', 0)->update(['descuento' => 20000]);

        $this->actingAs($this->usuario)
            ->postJson(route('tra-traslados.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recalcular_descuentos');
    }
}
