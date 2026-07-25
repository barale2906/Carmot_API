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
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Pago duplicado por error del cajero.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_ANULADO)
            ->assertJsonPath('data.motivo_anulacion', 'Pago duplicado por error del cajero.');

        $this->assertDatabaseHas('recibos_pago', [
            'id'               => $recibo->id,
            'status'           => ReciboPago::STATUS_ANULADO,
            'motivo_anulacion' => 'Pago duplicado por error del cajero.',
        ]);
    }

    /** @test */
    public function rechaza_anular_sin_motivo_de_anulacion(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_anulacion']);
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
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Error en el registro de pago.',
            ])
            ->assertOk();

        $cartera->refresh();
        $this->assertEquals(100000, $cartera->saldo);
        $this->assertEquals(Cartera::getStatusKey('Activa'), $cartera->status);
    }

    /** @test */
    public function anular_recibo_revierte_descuento_pronto_pago(): void
    {
        // Escenario: cuota de 100 000, descuento 5 000, pago neto 95 000 → saldo 0
        $conceptoDescuento = ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        $recibo  = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);
        $cartera = Cartera::factory()->create([
            'matricula_id'  => $this->matricula->id,
            'sede_id'       => $this->sede->id,
            'estudiante_id' => $this->matricula->estudiante_id,
            'valor'         => 100000,
            'saldo'         => 0,
            'abono'         => 95000,
            'descuento'     => 5000,
            'status'        => Cartera::getStatusKey('Cerrada'),
        ]);

        // Línea de abono
        $recibo->conceptosPago()->attach($this->conceptoMatricula->id, [
            'tipo'          => 0,
            'valor'         => 95000,
            'cantidad'      => 1,
            'unitario'      => 95000,
            'subtotal'      => 95000,
            'id_relacional' => $cartera->id,
            'observaciones' => 'Pago cuota 0',
        ]);

        // Línea de descuento pronto pago
        $recibo->conceptosPago()->attach($conceptoDescuento->id, [
            'tipo'          => 0,
            'valor'         => 5000,
            'cantidad'      => 1,
            'unitario'      => 5000,
            'subtotal'      => 5000,
            'id_relacional' => $cartera->id,
            'observaciones' => 'Descuento pronto pago',
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Pago ingresado en recibo equivocado.',
            ])
            ->assertOk();

        $cartera->refresh();
        $this->assertEquals(0,      $cartera->abono,    'El abono debe quedar en 0');
        $this->assertEquals(0,      $cartera->descuento, 'El descuento debe quedar en 0');
        $this->assertEquals(100000, $cartera->saldo,    'El saldo debe quedar en el valor original');
        $this->assertEquals(Cartera::getStatusKey('Activa'), $cartera->status);
    }

    /** @test */
    public function no_puede_anular_un_recibo_ya_anulado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_ANULADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Intento de re-anulación.',
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function no_puede_anular_un_recibo_cerrado(): void
    {
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CERRADO]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Cierre equivocado.',
            ])
            ->assertUnprocessable();
    }

    /** @test */
    public function rechaza_anular_sin_permiso(): void
    {
        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo('fin_recibos_pago');
        $recibo = $this->crearRecibo(['status' => ReciboPago::STATUS_CREADO]);

        $this->actingAs($sinPermiso)
            ->postJson(route('recibos-pago.anular', $recibo), [
                'motivo_anulacion' => 'Sin permiso.',
            ])
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
        // Descuento por pronto pago activo, 3 días de anticipación requeridos
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Cuota próxima 6 días adelante (> 3 días de anticipación requeridos)
        $cartera = Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'saldo'            => 100000,
            'fecha_vencimiento' => now()->addDays(6)->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
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
    public function precalcular_descuento_retorna_aplica_false_cuando_solo_hay_cuota_vencida_sin_proximas(): void
    {
        // Solo existe una cuota vencida; no hay cuotas próximas que califiquen para pronto pago.
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);

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
    public function precalcular_descuento_aplica_pronto_pago_a_cuotas_proximas_aunque_haya_vencidas(): void
    {
        // Escenario: cuota 1 vencida ayer + cuota 2 próxima con anticipación suficiente.
        // El pronto pago debe aplicar a cuota 2 aunque cuota 1 esté vencida.
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 5,
        ]);

        // Cuota 1: vencida ayer
        Cartera::factory()->create([
            'matricula_id'    => $this->matricula->id,
            'sede_id'         => $this->sede->id,
            'estudiante_id'   => $this->matricula->estudiante_id,
            'numero_cuota'    => 1,
            'valor'           => 100000,
            'saldo'           => 100000,
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'status'          => Cartera::getStatusKey('Activa'),
        ]);

        // Cuota 2: proxima en 30 días (cumple 5 días de anticipación)
        Cartera::factory()->create([
            'matricula_id'    => $this->matricula->id,
            'sede_id'         => $this->sede->id,
            'estudiante_id'   => $this->matricula->estudiante_id,
            'numero_cuota'    => 2,
            'valor'           => 100000,
            'saldo'           => 100000,
            'fecha_vencimiento' => now()->addDays(30)->toDateString(),
            'status'          => Cartera::getStatusKey('Activa'),
        ]);

        // Paga $190.000: $100.000 cuota1 (bruto, vencida) + $90.000 cuota2 (neto con 10% descuento)
        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 190000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', true)
            ->assertJson(fn ($json) => $json->where('data.valor', fn ($v) => abs($v - 10000) < 0.01)->etc());
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
        // Descuento del 10 %, 3 días de anticipación requeridos
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Dos cuotas próximas (6 y 11 días adelante, ambas > 3 días requeridos)
        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num + 1)->toDateString(),
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
        // 3 días de anticipación requeridos
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Dos cuotas próximas (6 y 11 días adelante)
        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num + 1)->toDateString(),
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
    public function precalcular_descuento_no_aplica_cuando_dias_anticipacion_insuficientes(): void
    {
        // Descuento requiere 5 días de anticipación
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 5,
        ]);

        // Cuota que vence HOY (0 días de anticipación, se necesitan 5)
        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'saldo'            => 100000,
            'fecha_vencimiento' => now()->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
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
    public function precalcular_descuento_no_aplica_a_cuota_0_matricula(): void
    {
        // Descuento de pronto pago (solo aplica a cuotas mensuales)
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Solo cuota 0 (matrícula) pendiente — no hay cuotas mensuales
        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 0,
            'valor'            => 150000,
            'saldo'            => 150000,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
        ]);

        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.precalcular-descuento'), [
                'matricula_id'  => $this->matricula->id,
                'monto_a_pagar' => 150000,
            ])
            ->assertOk()
            ->assertJsonPath('data.aplica', false);
    }

    /** @test */
    public function store_aplica_descuento_a_multiples_cuotas_proximas_cubiertas(): void
    {
        // 3 días de anticipación requeridos
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Cuotas 6 y 11 días adelante (> 3 días requeridos)
        foreach ([1, 2] as $num) {
            Cartera::factory()->create([
                'matricula_id'     => $this->matricula->id,
                'sede_id'          => $this->sede->id,
                'estudiante_id'    => $this->matricula->estudiante_id,
                'numero_cuota'     => $num,
                'valor'            => 100000,
                'saldo'            => 100000,
                'fecha_vencimiento' => now()->addDays(5 * $num + 1)->toDateString(),
                'status'           => Cartera::getStatusKey('Activa'),
            ]);
        }

        // Usuario paga el monto neto: 2 cuotas × ($100 000 − 10 %) = 2 × $90 000 = $180 000
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(180000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 180000]],
            ]))
            ->assertCreated();

        // descuento_total = 10 % × 100 000 × 2 = 20 000
        $this->assertEquals(20000, $response->json('data.descuento_total'));
    }

    /** @test */
    public function store_aplica_descuento_promocion_matricula_a_cuota_0(): void
    {
        // Descuento de matrícula: $50 000 fijo por promoción
        Descuento::factory()->vigente()->promocionMatricula()->create([
            'tipo'       => Descuento::TIPO_VALOR_FIJO,
            'valor'      => 50000,
            'aplicacion' => Descuento::APLICACION_MATRICULA,
        ]);

        // Cuota 0 (matrícula) pendiente
        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 0,
            'valor'            => 150000,
            'saldo'            => 150000,
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
        ]);

        // El concepto DESCUENTO es necesario para la línea de descuento
        ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        // Usuario paga el monto neto: $150 000 − $50 000 descuento = $100 000
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(100000))
            ->assertCreated();

        // El descuento de matrícula ($50 000) debe quedar registrado en descuento_total
        $this->assertEquals(50000, $response->json('data.descuento_total'));
    }

    /** @test */
    public function store_no_aplica_pronto_pago_a_cuota_0(): void
    {
        // Descuento de pronto pago (solo cuotas mensuales)
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 10,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        // Cuota 0 y cuota 1 pendientes
        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 0,
            'valor'            => 100000,
            'saldo'            => 100000,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
        ]);

        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'saldo'            => 100000,
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'status'           => Cartera::getStatusKey('Activa'),
        ]);

        ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        // Usuario paga el neto: $100 000 (cuota 0 sin desc.) + $90 000 (cuota 1 con 10 %) = $190 000
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(190000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 190000]],
            ]))
            ->assertCreated();

        // Solo cuota 1 recibe el descuento: 10 % × 100 000 = 10 000 (cuota 0 queda excluida)
        $this->assertEquals(10000, $response->json('data.descuento_total'));
    }

    /** @test */
    public function store_aplica_pronto_pago_al_saldo_restante_de_cuota_abonada(): void
    {
        // Cuota con abono previo: valor $100 000, ya se habían pagado $80 000, saldo $20 000.
        // El estudiante quiere pagar el saldo restante antes del vencimiento: debe recibir el
        // descuento de pronto pago calculado sobre el saldo ($20 000), no sobre el valor bruto.
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 5,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        $cartera = Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'abono'            => 80000,
            'descuento'        => 0,
            'saldo'            => 20000,
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'status'           => Cartera::getStatusKey('Abonada'),
        ]);

        ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        // Descuento sobre valor bruto: 5 % × $100 000 = $5 000 → el estudiante paga $15 000
        // (precio con descuento $95 000 − abono previo $80 000 = $15 000 pendiente)
        $response = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(15000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 15000]],
            ]))
            ->assertCreated();

        // descuento_total = 5 % × valor bruto $100 000 = $5 000 (no solo del saldo $20 000)
        $this->assertEquals(5000, $response->json('data.descuento_total'));

        // La cuota debe quedar completamente cerrada
        $cartera->refresh();
        $this->assertEquals(0, $cartera->saldo, 'El saldo debe ser 0 tras cerrar la cuota abonada');
        $this->assertEquals(Cartera::getStatusKey('Cerrada'), $cartera->status);
        $this->assertEquals(95000, $cartera->abono,    'El abono total debe ser $80 000 previo + $15 000 nuevo');
        $this->assertEquals(5000,  $cartera->descuento, 'El descuento debe ser $5 000 (5 % del valor bruto)');
    }

    /** @test */
    public function store_rechaza_monto_mayor_al_costo_efectivo_cuando_hay_descuento(): void
    {
        // Última cuota: valor $100 000, abono previo $90 000, saldo $10 000.
        // Con pronto pago 5 % sobre el bruto: descuento = $5 000, costo efectivo = $5 000.
        // El operador no debe poder registrar $10 000 porque el excedente ($5 000) no
        // tiene cuota a la que distribuirse — el sistema debe rechazar el monto.
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 5,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'abono'            => 90000,
            'descuento'        => 0,
            'saldo'            => 10000,
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'status'           => Cartera::getStatusKey('Abonada'),
        ]);

        ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        // Intenta pagar el saldo bruto ($10 000): excede el costo efectivo ($5 000)
        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(10000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 10000]],
            ]))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'El monto (10000) supera el costo efectivo de las cuotas pendientes. El máximo a pagar es 5000.']);
    }

    /** @test */
    public function anular_recibo_preserva_abono_previo_de_cuota_abonada(): void
    {
        // Verifica que al anular un recibo que completó una cuota Abonada, el saldo
        // vuelva a $20 000 (el restante antes del pago) y no a $100 000 (valor bruto).
        // Esto prueba que anular() usa pivot->valor (abono neto), no pivot->subtotal (bruto).
        Descuento::factory()->vigente()->pagoAnticipado()->create([
            'tipo'             => Descuento::TIPO_PORCENTUAL,
            'valor'            => 5,
            'aplicacion'       => Descuento::APLICACION_CUOTA,
            'dias_anticipacion' => 3,
        ]);

        $cartera = Cartera::factory()->create([
            'matricula_id'     => $this->matricula->id,
            'sede_id'          => $this->sede->id,
            'estudiante_id'    => $this->matricula->estudiante_id,
            'numero_cuota'     => 1,
            'valor'            => 100000,
            'abono'            => 80000,
            'descuento'        => 0,
            'saldo'            => 20000,
            'fecha_vencimiento' => now()->addDays(15)->toDateString(),
            'status'           => Cartera::getStatusKey('Abonada'),
        ]);

        ConceptoPago::factory()->tipoCartera()->create([
            'nombre' => ConceptoPago::DESCUENTO,
            'valor'  => 0,
        ]);

        // Crear el recibo cerrando la cuota abonada (paga el neto = $15 000)
        $storeResponse = $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.store'), $this->payloadCartera(15000, [
                'aplicar_descuento' => true,
                'medios_pago'       => [['medio_pago' => 'efectivo', 'valor' => 15000]],
            ]))
            ->assertCreated();

        $reciboId = $storeResponse->json('data.id');

        // Anular el recibo
        $this->actingAs($this->usuario)
            ->postJson(route('recibos-pago.anular', $reciboId), [
                'motivo_anulacion' => 'Pago duplicado, se debe revertir.',
            ])
            ->assertOk();

        // La cuota debe volver a su estado anterior: Abonada, saldo $20 000, abono $80 000
        $cartera->refresh();
        $this->assertEquals(20000, $cartera->saldo,    'El saldo debe restaurarse a $20 000 (no al valor bruto $100 000)');
        $this->assertEquals(80000, $cartera->abono,    'El abono previo de $80 000 debe conservarse');
        $this->assertEquals(0,     $cartera->descuento, 'El descuento debe revertirse a 0');
        $this->assertEquals(Cartera::getStatusKey('Abonada'), $cartera->status, 'Debe volver a estado Abonada');
    }
}
