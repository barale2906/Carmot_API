<?php

namespace Tests\Feature\Api\Financiero\ReciboPago;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Banco;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\ConceptoPago\ConceptoPago;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas del flujo de aprobación de recibos por transferencia.
 */
class ReciboTransferenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;
    private User $validador;
    private Sede $sede;
    private Matricula $matricula;
    private Banco $banco;
    private ConceptoPago $conceptoMatricula;
    private ConceptoPago $conceptoMensualidad;
    private ConceptoPago $conceptoDescuento;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'fin_recibos_pago'       => 'ver recibos',
            'fin_reciboPagoCrear'    => 'crear recibo',
            'fin_reciboPagoEditar'   => 'editar recibo',
            'fin_reciboPagoAnular'   => 'anular recibo',
            'fin_reciboPagoCerrar'   => 'cerrar recibo',
            'fin_reciboPagoAprobar'  => 'aprobar transferencia',
        ];
        foreach ($permisos as $nombre => $desc) {
            Permission::create(['name' => $nombre, 'descripcion' => $desc]);
        }

        $this->cajero = User::factory()->create();
        $this->cajero->givePermissionTo(['fin_recibos_pago', 'fin_reciboPagoCrear', 'fin_reciboPagoEditar', 'fin_reciboPagoAnular']);

        $this->validador = User::factory()->create();
        $this->validador->givePermissionTo(['fin_recibos_pago', 'fin_reciboPagoAprobar']);

        $this->sede = Sede::factory()->create([
            'codigo_academico'  => 'TEST',
            'codigo_inventario' => 'INV',
        ]);

        $this->banco = Banco::factory()->create(['nombre' => 'Bancolombia', 'codigo' => '007']);

        $this->matricula = Matricula::factory()->create([
            'status'                => 1,
            'lp_precio_producto_id' => null,
        ]);

        $this->conceptoMatricula   = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Matrícula',          'valor' => 0]);
        $this->conceptoMensualidad = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Pago de mensualidad', 'valor' => 0]);
        $this->conceptoDescuento   = ConceptoPago::factory()->tipoCartera()->create(['nombre' => 'Descuento',          'valor' => 0]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function crearReciboPendiente(array $overrides = []): ReciboPago
    {
        $recibo = ReciboPago::factory()->pendienteAprobacion()->create(array_merge([
            'sede_id'      => $this->sede->id,
            'cajero_id'    => $this->cajero->id,
            'matricula_id' => $this->matricula->id,
            'valor_total'  => 100000,
        ], $overrides));

        ReciboPagoMedioPago::create([
            'recibo_pago_id'     => $recibo->id,
            'medio_pago'         => 'transferencia',
            'valor'              => 100000,
            'banco_id'           => $this->banco->id,
            'numero_transaccion' => 'TXN-' . $recibo->id . '-001',
        ]);

        return $recibo;
    }

    private function crearCartera(array $attrs = []): Cartera
    {
        return Cartera::factory()->create(array_merge([
            'matricula_id' => $this->matricula->id,
            'valor'        => 100000,
            'saldo'        => 100000,
            'numero_cuota' => 1,
        ], $attrs));
    }

    // ─── store con transferencia ──────────────────────────────────────────────

    /** @test */
    public function store_crea_recibo_pendiente_al_elegir_transferencia(): void
    {
        Storage::fake('public');

        $payload = [
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->cajero->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->format('Y-m-d'),
            'fecha_transaccion' => now()->format('Y-m-d H:i:s'),
            'monto_a_pagar'     => 100000,
            'medios_pago'       => [[
                'medio_pago'         => 'transferencia',
                'valor'              => 100000,
                'banco_id'           => $this->banco->id,
                'numero_transaccion' => 'TXN-TEST-001',
            ]],
        ];

        $response = $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.store'), $payload)
            ->assertCreated();

        $this->assertEquals(ReciboPago::STATUS_PENDIENTE_APROBACION, $response->json('data.status'));
        $this->assertNull($response->json('data.numero_recibo'));

        $this->assertDatabaseHas('recibos_pago', [
            'status'        => ReciboPago::STATUS_PENDIENTE_APROBACION,
            'numero_recibo' => null,
        ]);
        $this->assertDatabaseHas('recibo_pago_medio_pago', [
            'medio_pago'         => 'transferencia',
            'banco_id'           => $this->banco->id,
            'numero_transaccion' => 'TXN-TEST-001',
        ]);
    }

    /** @test */
    public function store_rechaza_transferencia_combinada_con_otro_medio(): void
    {
        $payload = [
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->cajero->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->format('Y-m-d'),
            'fecha_transaccion' => now()->format('Y-m-d H:i:s'),
            'monto_a_pagar'     => 100000,
            'medios_pago'       => [
                ['medio_pago' => 'transferencia', 'valor' => 50000, 'banco_id' => $this->banco->id, 'numero_transaccion' => 'TXN-1'],
                ['medio_pago' => 'efectivo',       'valor' => 50000],
            ],
        ];

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medios_pago']);
    }

    /** @test */
    public function store_rechaza_transferencia_sin_banco_id(): void
    {
        $payload = [
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->cajero->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->format('Y-m-d'),
            'fecha_transaccion' => now()->format('Y-m-d H:i:s'),
            'monto_a_pagar'     => 100000,
            'medios_pago'       => [['medio_pago' => 'transferencia', 'valor' => 100000, 'numero_transaccion' => 'TXN-001']],
        ];

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.store'), $payload)
            ->assertUnprocessable();
    }

    /** @test */
    public function store_rechaza_numero_transaccion_duplicado(): void
    {
        // Crear un medio de pago existente con ese número de transacción (sin factory — no existe)
        $reciboBase = ReciboPago::factory()->creado()->create([
            'sede_id'   => $this->sede->id,
            'cajero_id' => $this->cajero->id,
        ]);
        ReciboPagoMedioPago::create([
            'recibo_pago_id'     => $reciboBase->id,
            'medio_pago'         => 'transferencia',
            'valor'              => 100000,
            'numero_transaccion' => 'TXN-DUPLICADO',
        ]);

        $payload = [
            'sede_id'           => $this->sede->id,
            'cajero_id'         => $this->cajero->id,
            'matricula_id'      => $this->matricula->id,
            'origen'            => ReciboPago::ORIGEN_ACADEMICO,
            'fecha_recibo'      => now()->format('Y-m-d'),
            'fecha_transaccion' => now()->format('Y-m-d H:i:s'),
            'monto_a_pagar'     => 100000,
            'medios_pago'       => [[
                'medio_pago'         => 'transferencia',
                'valor'              => 100000,
                'banco_id'           => $this->banco->id,
                'numero_transaccion' => 'TXN-DUPLICADO',
            ]],
        ];

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medios_pago.0.numero_transaccion']);
    }

    // ─── notificarTransferencia ───────────────────────────────────────────────

    /** @test */
    public function notificar_transferencia_envia_notificacion_a_aprobadores(): void
    {
        Notification::fake();

        $recibo = $this->crearReciboPendiente();

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.notificar-transferencia', $recibo))
            ->assertOk()
            ->assertJsonPath('aprobadores', 1);

        Notification::assertSentTo(
            $this->validador,
            \App\Notifications\Financiero\TransferenciaPendienteNotification::class
        );
    }

    /** @test */
    public function notificar_falla_si_recibo_no_esta_pendiente(): void
    {
        $recibo = ReciboPago::factory()->creado()->create([
            'sede_id'   => $this->sede->id,
            'cajero_id' => $this->cajero->id,
        ]);

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.notificar-transferencia', $recibo))
            ->assertUnprocessable();
    }

    // ─── pendientesTransferencia ──────────────────────────────────────────────

    /** @test */
    public function pendientes_transferencia_lista_solo_pendientes(): void
    {
        $pendiente = $this->crearReciboPendiente();
        ReciboPago::factory()->creado()->create(['sede_id' => $this->sede->id, 'cajero_id' => $this->cajero->id]);

        $response = $this->actingAs($this->validador)
            ->getJson(route('recibos-pago.pendientes-transferencia'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($pendiente->id, $response->json('data.0.id'));
    }

    /** @test */
    public function pendientes_transferencia_deniega_sin_permiso_aprobacion(): void
    {
        $this->actingAs($this->cajero)
            ->getJson(route('recibos-pago.pendientes-transferencia'))
            ->assertForbidden();
    }

    // ─── aprobarTransferencia ────────────────────────────────────────────────

    /** @test */
    public function aprobar_transferencia_asigna_numero_y_cierra_recibo(): void
    {
        Notification::fake();

        $cartera = $this->crearCartera();
        $recibo  = $this->crearReciboPendiente();

        $this->actingAs($this->validador)
            ->postJson(route('recibos-pago.aprobar-transferencia', $recibo))
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_CREADO);

        $recibo->refresh();
        $this->assertEquals(ReciboPago::STATUS_CREADO, $recibo->status);
        $this->assertNotNull($recibo->numero_recibo);
        $this->assertNotNull($recibo->fecha_aprobacion);
        $this->assertEquals($this->validador->id, $recibo->aprobado_por_id);

        Notification::assertSentTo(
            $this->cajero,
            \App\Notifications\Financiero\TransferenciaAprobadaNotification::class
        );
    }

    /** @test */
    public function aprobar_falla_si_recibo_no_esta_pendiente(): void
    {
        $recibo = ReciboPago::factory()->creado()->create([
            'sede_id'   => $this->sede->id,
            'cajero_id' => $this->cajero->id,
        ]);

        $this->actingAs($this->validador)
            ->postJson(route('recibos-pago.aprobar-transferencia', $recibo))
            ->assertUnprocessable();
    }

    /** @test */
    public function aprobar_deniega_sin_permiso_aprobacion(): void
    {
        $recibo = $this->crearReciboPendiente();

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.aprobar-transferencia', $recibo))
            ->assertForbidden();
    }

    // ─── rechazarTransferencia ───────────────────────────────────────────────

    /** @test */
    public function rechazar_transferencia_pone_recibo_en_estado_rechazado(): void
    {
        Notification::fake();

        $recibo = $this->crearReciboPendiente();

        $this->actingAs($this->validador)
            ->postJson(route('recibos-pago.rechazar-transferencia', $recibo), [
                'motivo_rechazo' => 'El número de transacción no corresponde al comprobante.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_RECHAZADO);

        $recibo->refresh();
        $this->assertEquals(ReciboPago::STATUS_RECHAZADO, $recibo->status);
        $this->assertEquals('El número de transacción no corresponde al comprobante.', $recibo->motivo_rechazo);

        Notification::assertSentTo(
            $this->cajero,
            \App\Notifications\Financiero\TransferenciaRechazadaNotification::class
        );
    }

    /** @test */
    public function rechazar_requiere_motivo(): void
    {
        $recibo = $this->crearReciboPendiente();

        $this->actingAs($this->validador)
            ->postJson(route('recibos-pago.rechazar-transferencia', $recibo), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_rechazo']);
    }

    // ─── reenviarTransferencia ───────────────────────────────────────────────

    /** @test */
    public function reenviar_corrige_y_devuelve_a_pendiente_aprobacion(): void
    {
        Notification::fake();

        $recibo = $this->crearReciboPendiente();
        $recibo->update(['status' => ReciboPago::STATUS_RECHAZADO, 'motivo_rechazo' => 'Número incorrecto.']);

        $nuevoBanco = Banco::factory()->create(['nombre' => 'Davivienda']);

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.reenviar-transferencia', $recibo), [
                'banco_id'           => $nuevoBanco->id,
                'numero_transaccion' => 'TXN-CORREGIDO-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReciboPago::STATUS_PENDIENTE_APROBACION);

        $recibo->refresh();
        $this->assertEquals(ReciboPago::STATUS_PENDIENTE_APROBACION, $recibo->status);
        $this->assertNull($recibo->motivo_rechazo);

        Notification::assertSentTo(
            $this->validador,
            \App\Notifications\Financiero\TransferenciaPendienteNotification::class
        );
    }

    /** @test */
    public function reenviar_falla_si_recibo_no_esta_rechazado(): void
    {
        $recibo = $this->crearReciboPendiente();

        $this->actingAs($this->cajero)
            ->postJson(route('recibos-pago.reenviar-transferencia', $recibo), [
                'numero_transaccion' => 'NUEVA-TXN',
            ])
            ->assertUnprocessable();
    }

    // ─── destroy con transferencia ────────────────────────────────────────────

    /** @test */
    public function destroy_elimina_recibo_rechazado_y_borra_comprobante(): void
    {
        Storage::fake('public');

        $recibo = $this->crearReciboPendiente();
        $recibo->update(['status' => ReciboPago::STATUS_RECHAZADO]);

        $path = "recibos_transferencia/{$recibo->id}/comprobante.jpg";
        Storage::disk('public')->put($path, 'contenido');
        $recibo->mediosPago()->update(['comprobante_path' => $path]);

        $this->actingAs($this->cajero)
            ->deleteJson(route('recibos-pago.destroy', $recibo))
            ->assertOk();

        $this->assertSoftDeleted('recibos_pago', ['id' => $recibo->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
