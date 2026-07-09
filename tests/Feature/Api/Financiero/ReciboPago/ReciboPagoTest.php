<?php

namespace Tests\Feature\Api\Financiero\ReciboPago;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\Descuento\Descuento;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del módulo ReciboPago: modo unificado, anulación, cierre y permisos.
 */
class ReciboPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Sede $sede;
    private Matricula $matricula;
    private ConceptoPago $conceptoMatricula;
    private ConceptoPago $conceptoMensualidad;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'fin_recibos_pago',     'descripcion' => 'ver recibos de pago']);
        Permission::create(['name' => 'fin_reciboPagoCrear',  'descripcion' => 'crear recibo de pago']);
        Permission::create(['name' => 'fin_reciboPagoEditar', 'descripcion' => 'editar recibo de pago']);
        Permission::create(['name' => 'fin_reciboPagoAnular', 'descripcion' => 'anular recibo de pago']);
        Permission::create(['name' => 'fin_reciboPagoCerrar', 'descripcion' => 'cerrar recibo de pago']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            'fin_recibos_pago',
            'fin_reciboPagoCrear',
            'fin_reciboPagoEditar',
            'fin_reciboPagoAnular',
            'fin_reciboPagoCerrar',
        ]);

        $this->sede = Sede::factory()->create([
            'codigo_academico'  => 'TEST',
            'codigo_inventario' => 'INV',
        ]);

        // Matrícula sin LP para no disparar CarteraGeneradorService en setUp
        $this->matricula = Matricula::factory()->create([
            'status'                => 1,
            'lp_precio_producto_id' => null,
        ]);

        // Conceptos usados por los servicios de distribución de cartera
        $this->conceptoMatricula   = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Matrícula',          'valor' => 0]);
        $this->conceptoMensualidad = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Pago de mensualidad', 'valor' => 0]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function crearRecibo(array $attrs = []): ReciboPago
    {
        return ReciboPago::factory()->create(array_merge([
            'sede_id'   => $this->sede->id,
            'cajero_id' => $this->usuario->id,
        ], $attrs));
    }

    /** Payload mínimo del modo unificado: solo conceptos adicionales, sin cartera. */
    private function payloadSoloAdicionales(ConceptoPago $concepto, array $overrides = []): array
    {
        return array_merge([
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->usuario->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->toDateString(),
            'fecha_transaccion' => now()->toDateString(),
            'monto_a_pagar'     => (float) $concepto->valor,
            'conceptos_adicionales' => [
                ['concepto_pago_id' => $concepto->id, 'cantidad' => 1],
            ],
            'medios_pago' => [
                ['medio_pago' => 'efectivo', 'valor' => (float) $concepto->valor],
            ],
        ], $overrides);
    }

    /** Payload de distribución a cartera (sin conceptos adicionales). */
    private function payloadCartera(float $monto, array $overrides = []): array
    {
        return array_merge([
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->usuario->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->toDateString(),
            'fecha_transaccion' => now()->toDateString(),
            'monto_a_pagar'     => $monto,
            'medios_pago'       => [
                ['medio_pago' => 'efectivo', 'valor' => $monto],
            ],
        ], $overrides);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    /** @test */
    public function lista_recibos_de_pago_paginados(): void
    {
        $this->crearRecibo();
        $this->crearRecibo();

        $this->actingAs($this->usuario)
            ->getJson(route('recibos-pago.index'))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @test */
    public function rechaza_index_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('recibos-pago.index'))
            ->assertForbidden();
    }

    /** @test */
    public function filtra_recibos_por_sede_id(): void
    {
        $this->crearRecibo();
        $otraSede = Sede::factory()->create([
            'codigo_academico'  => 'OTR',
            'codigo_inventario' => 'OTRI',
        ]);
        ReciboPago::factory()->create(['sede_id' => $otraSede->id, 'cajero_id' => $this->usuario->id]);

        $response = $this->actingAs($this->usuario)
            ->getJson(route('recibos-pago.index', ['sede_id' => $this->sede->id]));

        $response->assertOk();
        foreach ($response->json('data') as $item) {
            $this->assertEquals($this->sede->id, $item['sede_id']);
        }
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    /** @test */
    public function muestra_un_recibo_de_pago(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($this->usuario)
            ->getJson(route('recibos-pago.show', $recibo))
            ->assertOk()
            ->assertJsonPath('data.id', $recibo->id);
    }

    // ─── store — modo unificado ───────────────────────────────────────────────

    /** @test */
    public function crea_recibo_con_solo_concepto_adicional(): void
    {
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Copia Certificado', 'tipo' => 1, 'valor' => 25000]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadSoloAdicionales($concepto));

        $response->assertCreated()
            ->assertJsonPath('data.status', ReciboPago::STATUS_CREADO);

        $this->assertEquals(25000, $response->json('data.valor_total'));

        $this->assertDatabaseHas('recibos_pago', [
            'sede_id'    => $this->sede->id,
            'valor_total' => 25000,
        ]);
    }

    /** @test */
    public function crea_recibo_distribuyendo_cartera(): void
    {
        // Cuota de matrícula pendiente
        $cartera = Cartera::factory()->create([
            'matricula_id'  => $this->matricula->id,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $this->matricula->estudiante_id,
            'numero_cuota'  => 0,
            'valor'         => 200000,
            'saldo'         => 200000,
            'status'        => Cartera::getStatusKey('Activa'),
        ]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(200000));

        $response->assertCreated();
        $this->assertEquals(200000, $response->json('data.valor_total'));

        // La cartera debe quedar cerrada
        $this->assertDatabaseHas('carteras', [
            'id'     => $cartera->id,
            'saldo'  => 0,
            'status' => Cartera::getStatusKey('Cerrada'),
        ]);
    }

    /** @test */
    public function crea_recibo_mixto_adicional_mas_cartera(): void
    {
        $conceptoExtra = ConceptoPago::factory()->create(['nombre' => 'Copia Diploma', 'tipo' => 1, 'valor' => 10000]);

        Cartera::factory()->create([
            'matricula_id'  => $this->matricula->id,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $this->matricula->estudiante_id,
            'numero_cuota'  => 1,
            'valor'         => 100000,
            'saldo'         => 100000,
            'status'        => Cartera::getStatusKey('Activa'),
        ]);

        // Paga 110000: 10000 certificado + 100000 cuota
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), [
                'sede_id'           => $this->sede->id,
                'cajero_id'         => $this->usuario->id,
                'matricula_id'      => $this->matricula->id,
                'origen'            => ReciboPago::ORIGEN_ACADEMICO,
                'fecha_recibo'      => now()->toDateString(),
                'fecha_transaccion' => now()->toDateString(),
                'monto_a_pagar'     => 110000,
                'conceptos_adicionales' => [
                    ['concepto_pago_id' => $conceptoExtra->id, 'cantidad' => 1],
                ],
                'medios_pago' => [
                    ['medio_pago' => 'efectivo', 'valor' => 110000],
                ],
            ]);

        $response->assertCreated();
        $this->assertEquals(110000, $response->json('data.valor_total'));
    }

    /** @test */
    public function rechaza_monto_insuficiente_para_conceptos_adicionales(): void
    {
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Copia', 'tipo' => 1, 'valor' => 50000]);

        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), [
                'sede_id'           => $this->sede->id,
                'cajero_id'         => $this->usuario->id,
                'matricula_id'      => $this->matricula->id,
                'origen'            => ReciboPago::ORIGEN_ACADEMICO,
                'fecha_recibo'      => now()->toDateString(),
                'fecha_transaccion' => now()->toDateString(),
                'monto_a_pagar'     => 10000, // menor que 50000
                'conceptos_adicionales' => [
                    ['concepto_pago_id' => $concepto->id, 'cantidad' => 1],
                ],
                'medios_pago' => [
                    ['medio_pago' => 'efectivo', 'valor' => 10000],
                ],
            ]);

        $response->assertUnprocessable();
    }

    /** @test */
    public function rechaza_cuando_suma_medios_pago_no_iguala_monto(): void
    {
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Copia', 'tipo' => 1, 'valor' => 50000]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), [
                'sede_id'           => $this->sede->id,
                'cajero_id'         => $this->usuario->id,
                'matricula_id'      => $this->matricula->id,
                'origen'            => ReciboPago::ORIGEN_ACADEMICO,
                'fecha_recibo'      => now()->toDateString(),
                'fecha_transaccion' => now()->toDateString(),
                'monto_a_pagar'     => 50000,
                'conceptos_adicionales' => [
                    ['concepto_pago_id' => $concepto->id, 'cantidad' => 1],
                ],
                'medios_pago' => [
                    ['medio_pago' => 'efectivo', 'valor' => 30000], // ≠ 50000
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medios_pago']);
    }

    /** @test */
    public function validacion_falla_sin_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sede_id', 'cajero_id', 'matricula_id',
                'origen', 'fecha_recibo', 'fecha_transaccion',
                'monto_a_pagar', 'medios_pago',
            ]);
    }

    /** @test */
    public function validacion_falla_con_origen_invalido(): void
    {
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Copia', 'tipo' => 1, 'valor' => 10000]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadSoloAdicionales($concepto, ['origen' => 99]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['origen']);
    }

    /** @test */
    public function rechaza_crear_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_recibos_pago');

        $concepto = ConceptoPago::factory()->create(['nombre' => 'Copia', 'tipo' => 1, 'valor' => 10000]);

        $this->actingAs($sinPermiso)
            ->postJson(route('recibos-pago.store'), $this->payloadSoloAdicionales($concepto))
            ->assertForbidden();
    }

    // ─── anular ───────────────────────────────────────────────────────────────

    /** @test */
    public function anula_un_recibo_creado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo))
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_ANULADO);

        $this->assertDatabaseHas('recibos_pago', [
            'id'     => $recibo->id,
            'status' => ReciboPago::STATUS_ANULADO,
        ]);
    }

    /** @test */
    public function anular_recibo_revierte_carteras_afectadas(): void
    {
        // Crear recibo y cartera vinculados vía pivot id_relacional
        $recibo  = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);
        $cartera = Cartera::factory()->create([
            'matricula_id'  => $this->matricula->id,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $this->matricula->estudiante_id,
            'valor'         => 100000,
            'saldo'         => 0,
            'abono'         => 100000,
            'status'        => Cartera::getStatusKey('Cerrada'),
        ]);

        // Vincular concepto con la cartera vía pivot (tipo 0 = Cartera)
        $recibo->conceptosPago()->attach($this->conceptoMatricula->id, [
            'tipo'          => 0,
            'valor'         => 100000,
            'cantidad'      => 1,
            'unitario'      => 100000,
            'subtotal'      => 100000,
            'id_relacional' => $cartera->id,
            'observaciones' => 'test',
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo))
            ->assertOk();

        $cartera->refresh();
        $this->assertEquals(100000, $cartera->saldo);
        $this->assertEquals(Cartera::getStatusKey('Activa'), $cartera->status);
    }

    /** @test */
    public function no_puede_anular_un_recibo_ya_anulado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_ANULADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo))
            ->assertUnprocessable();
    }

    /** @test */
    public function no_puede_anular_un_recibo_cerrado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CERRADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo))
            ->assertUnprocessable();
    }

    /** @test */
    public function rechaza_anular_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_recibos_pago');
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($sinPermiso)
            ->postJson(route('recibos-pago.anular', $recibo))
            ->assertForbidden();
    }

    // ─── cerrar ───────────────────────────────────────────────────────────────

    /** @test */
    public function cierra_un_recibo_creado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.cerrar', $recibo), ['cierre' => 42])
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_CERRADO);

        $this->assertDatabaseHas('recibos_pago', [
            'id'     => $recibo->id,
            'status' => ReciboPago::STATUS_CERRADO,
        ]);
    }

    /** @test */
    public function no_puede_cerrar_un_recibo_anulado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_ANULADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.cerrar', $recibo))
            ->assertUnprocessable();
    }

    /** @test */
    public function rechaza_cerrar_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_recibos_pago');
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($sinPermiso)
            ->postJson(route('recibos-pago.cerrar', $recibo))
            ->assertForbidden();
    }

    // ─── precalcular-descuento ────────────────────────────────────────────────

    /** @test */
    public function precalcular_descuento_retorna_aplica_true_cuando_hay_descuento_activo_y_sin_mora(): void
    {
        // Descuento por pronto pago activo, aplicado a cuota
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'      => Descuento::TIPO_PORCENTUAL,
            'valor'     => 10,
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

        // Cuota próxima (no vencida)
        $cartera = Cartera::factory()->create([
            'matricula_id'    => $this->matricula->id,
            'sede_id'         => $this->sede->id,
            'estudiante_id'   => $this->matricula->estudiante_id,
            'numero_cuota'    => 1,
            'valor'           => 100000,
            'saldo'           => 100000,
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'status'          => Cartera::getStatusKey('Activa'),
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => $cartera->saldo,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', true)
            ->assertJsonPath('data.descuento.tipo', Descuento::TIPO_PORCENTUAL);
    }

    /** @test */
    public function precalcular_descuento_retorna_aplica_false_cuando_hay_cuotas_vencidas(): void
    {
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

        // Cuota vencida pendiente
        Cartera::factory()->create([
            'matricula_id'    => $this->matricula->id,
            'sede_id'         => $this->sede->id,
            'estudiante_id'   => $this->matricula->estudiante_id,
            'numero_cuota'    => 1,
            'valor'           => 100000,
            'saldo'           => 100000,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
            'status'          => Cartera::getStatusKey('Activa'),
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', false);
    }

    /** @test */
    public function precalcular_descuento_retorna_aplica_false_sin_descuento_activo(): void
    {
        Cartera::factory()->create([
            'matricula_id'    => $this->matricula->id,
            'sede_id'         => $this->sede->id,
            'estudiante_id'   => $this->matricula->estudiante_id,
            'numero_cuota'    => 1,
            'valor'           => 100000,
            'saldo'           => 100000,
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'status'          => Cartera::getStatusKey('Activa'),
        ]);

        // No existe ningún descuento activo de pronto pago

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 100000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', false);
    }

    /** @test */
    public function precalcular_descuento_valida_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['matricula_id', 'monto_a_pagar']);
    }

    /** @test */
    public function precalcular_descuento_suma_descuento_de_todas_las_cuotas_proximas_cubiertas(): void
    {
        // Descuento del 10 % por pronto pago
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'       => Descuento::TIPO_PORCENTUAL,
            'valor'      => 10,
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

        // Dos cuotas próximas
        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num)->toDateString(),
                'status'           => Cartera::getStatusKey('Activa'),
            ]);
        }

        // Paga las dos cuotas completas = 200 000
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 200000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', true);

        // Descuento total = 10 % × 100 000 × 2 = 20 000
        $this->assertEquals(20000, $response->json('data.valor'));
    }

    /** @test */
    public function precalcular_descuento_no_aplica_a_cuota_solo_cubierta_parcialmente(): void
    {
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'       => Descuento::TIPO_PORCENTUAL,
            'valor'      => 10,
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

        // Dos cuotas próximas de 100 000 cada una
        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num)->toDateString(),
                'status'           => Cartera::getStatusKey('Activa'),
            ]);
        }

        // Solo paga 150 000: cubre la primera completa y la segunda a medias
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 150000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', true);

        // Solo la primera cuota da descuento = 10 % × 100 000 = 10 000
        $this->assertEquals(10000, $response->json('data.valor'));
    }

    /** @test */
    public function store_aplica_descuento_a_multiples_cuotas_proximas_cubiertas(): void
    {
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'       => Descuento::TIPO_PORCENTUAL,
            'valor'      => 10,
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num)->toDateString(),
                'status'           => Cartera::getStatusKey('Activa'),
            ]);
        }

        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(200000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 200000]],
            ]))
            ->assertCreated();

        // descuento_total = 10 % × 100 000 × 2 = 20 000
        $this->assertEquals(20000, $response->json('data.descuento_total'));
    }
}
