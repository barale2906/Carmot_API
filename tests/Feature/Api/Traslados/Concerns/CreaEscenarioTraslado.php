<?php

namespace Tests\Feature\Api\Traslados\Concerns;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Curso;
use App\Models\Academico\Matricula;
use App\Models\Academico\Programacion;
use App\Models\Configuracion\Poblacion;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\Lp\LpListaPrecio;
use App\Models\Financiero\Lp\LpPrecioProducto;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpProductoReferencia;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Escenario común de las pruebas de traslados: dos sedes (en poblaciones distintas) y dos cursos,
 * con un ciclo activo en cada combinación (más un segundo ciclo en la sede A / curso X), una
 * programación y un precio de lista por combinación, y un estudiante matriculado en la sede A, curso X.
 *
 * El precio del curso X en la sede A es de 1.000.000: matrícula 200.000 y 4 cuotas de 200.000.
 * El estudiante ya pagó la matrícula y la primera cuota (400.000); quedan las cuotas 2, 3 y 4.
 */
trait CreaEscenarioTraslado
{
    protected const PERMISOS_TRASLADOS = ['tra_traslados', 'tra_trasladoCrear', 'tra_trasladoPagoAcordado', 'tra_trasladoNivelacion'];

    protected Sede $sedeA;

    protected Sede $sedeB;

    protected Curso $cursoX;

    protected Curso $cursoY;

    protected Ciclo $cicloA1;

    protected Ciclo $cicloA2;

    protected Ciclo $cicloB1;

    protected Ciclo $cicloAY;

    protected Ciclo $cicloBY;

    protected Programacion $progAX;

    protected Programacion $progBX;

    protected LpPrecioProducto $lpAX;

    protected LpPrecioProducto $lpBX;

    protected LpPrecioProducto $lpAY;

    protected LpPrecioProducto $lpBY;

    protected User $coordinador;

    protected User $gerente;

    protected User $sinPermisos;

    protected Matricula $matricula;

    /**
     * Crea permisos, usuarios, sedes, cursos, ciclos, programaciones, precios y la matrícula.
     */
    protected function crearEscenarioTraslado(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (self::PERMISOS_TRASLADOS as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sedeA = Sede::factory()->create(['poblacion_id' => Poblacion::factory()->create()->id]);
        $this->sedeB = Sede::factory()->create(['poblacion_id' => Poblacion::factory()->create()->id]);
        $this->cursoX = Curso::factory()->create();
        $this->cursoY = Curso::factory()->create();

        $this->cicloA1 = $this->crearCiclo($this->sedeA, $this->cursoX, 'Ciclo A1');
        $this->cicloA2 = $this->crearCiclo($this->sedeA, $this->cursoX, 'Ciclo A2');
        $this->cicloB1 = $this->crearCiclo($this->sedeB, $this->cursoX, 'Ciclo B1');
        $this->cicloAY = $this->crearCiclo($this->sedeA, $this->cursoY, 'Ciclo AY');
        $this->cicloBY = $this->crearCiclo($this->sedeB, $this->cursoY, 'Ciclo BY');

        $this->progAX = $this->crearProgramacion($this->sedeA, $this->cursoX, 2);
        $this->progBX = $this->crearProgramacion($this->sedeB, $this->cursoX, 0);
        $this->crearProgramacion($this->sedeA, $this->cursoY, 2);
        $this->crearProgramacion($this->sedeB, $this->cursoY, 0);

        $this->lpAX = $this->crearPrecio($this->sedeA, $this->cursoX, 1000000, 800000, 200000, 4);
        $this->lpBX = $this->crearPrecio($this->sedeB, $this->cursoX, 1300000, 1000000, 300000, 4);
        $this->lpAY = $this->crearPrecio($this->sedeA, $this->cursoY, 1500000, 1200000, 300000, 4);
        $this->lpBY = $this->crearPrecio($this->sedeB, $this->cursoY, 1500000, 1200000, 300000, 4);

        $this->coordinador = User::factory()->create();
        $this->coordinador->givePermissionTo(['tra_traslados', 'tra_trasladoCrear', 'tra_trasladoNivelacion']);
        $this->coordinador->sedes()->attach([$this->sedeA->id, $this->sedeB->id]);

        $this->gerente = User::factory()->create();
        $this->gerente->givePermissionTo(self::PERMISOS_TRASLADOS);
        $this->gerente->sedes()->attach([$this->sedeA->id, $this->sedeB->id]);

        $this->sinPermisos = User::factory()->create();

        $this->matricula = $this->crearMatricula();
        // Pagó la matrícula (cuota 0) y la primera cuota
        foreach ([0, 1] as $numero) {
            $this->matricula->carteras()->where('numero_cuota', $numero)->first()->aplicarPago(200000);
        }
    }

    /**
     * Ciclo activo que va de febrero a junio de 2027, con las notas abiertas.
     */
    protected function crearCiclo(Sede $sede, Curso $curso, string $nombre): Ciclo
    {
        return Ciclo::factory()->create([
            'sede_id' => $sede->id,
            'curso_id' => $curso->id,
            'nombre' => $nombre,
            'fecha_inicio' => '2027-02-01',
            'fecha_fin' => '2027-06-30',
            'fecha_fin_automatica' => false,
            'inscritos' => 0,
            'status' => 1,
        ]);
    }

    protected function crearProgramacion(Sede $sede, Curso $curso, int $jornada): Programacion
    {
        return Programacion::factory()->create([
            'sede_id' => $sede->id,
            'curso_id' => $curso->id,
            'jornada' => $jornada,
            'status' => 1,
        ]);
    }

    /**
     * Precio de lista vigente del curso para la población de la sede.
     */
    protected function crearPrecio(Sede $sede, Curso $curso, float $contado, ?float $total, float $matricula, ?int $cuotas): LpPrecioProducto
    {
        $lista = LpListaPrecio::factory()->activa()->create([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_fin' => now()->addYear()->toDateString(),
        ]);
        $lista->poblaciones()->attach($sede->poblacion_id);

        $producto = LpProducto::factory()->activo()->curso()->create();
        LpProductoReferencia::create([
            'lp_producto_id' => $producto->id,
            'referencia_id' => $curso->id,
            'referencia_tipo' => LpProductoReferencia::TIPO_CURSO,
        ]);

        return LpPrecioProducto::factory()->conValores($contado, $total, $matricula, $cuotas)->create([
            'lista_precio_id' => $lista->id,
            'producto_id' => $producto->id,
        ]);
    }

    /**
     * Matrícula vigente en la sede A, curso X, con su cartera generada por el precio de lista.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function crearMatricula(array $atributos = []): Matricula
    {
        return Matricula::factory()->create(array_merge([
            'sede_id' => $this->sedeA->id,
            'curso_id' => $this->cursoX->id,
            'ciclo_id' => $this->cicloA1->id,
            'programacion_id' => $this->progAX->id,
            'lp_precio_producto_id' => $this->lpAX->id,
            'estudiante_id' => User::factory()->create()->id,
            'fecha_matricula' => now()->subDays(10)->toDateString(),
            'fecha_inicio' => now()->subDays(5)->toDateString(),
            'monto' => 1000000,
            'status' => 1,
            'estado_academico' => Matricula::ESTADO_ACTIVO,
        ], $atributos));
    }

    /**
     * Cuerpo de un traslado a la sede B, mismo curso, con el precio de la sede B.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function datosTraslado(array $extra = []): array
    {
        return array_merge([
            'matricula_id' => $this->matricula->id,
            'sede_id' => $this->sedeB->id,
            'curso_id' => $this->cursoX->id,
            'ciclo_id' => $this->cicloB1->id,
            'lp_precio_producto_id' => $this->lpBX->id,
            'motivo' => 'Cambio de domicilio del estudiante',
        ], $extra);
    }

    protected function trasladar(array $extra = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->coordinador)->postJson(route('tra-traslados.store'), $this->datosTraslado($extra));
    }

    protected function simular(array $extra = [], ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->coordinador)->postJson(route('tra-traslados.simular'), $this->datosTraslado($extra));
    }

    /**
     * Cuotas de la matrícula en una sede, por número de cuota y estado.
     */
    protected function carteras(?int $sedeId = null, ?int $status = null)
    {
        return Cartera::where('matricula_id', $this->matricula->id)
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderBy('numero_cuota')
            ->get();
    }
}
