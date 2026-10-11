<?php

namespace Tests\Feature\Api\Financiero\LibroDiario;

use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Financiero\ReciboPago\ReciboPagoMedioPago;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Preparación común de las pruebas del libro diario: permisos del módulo,
 * usuarios con sede asignada y recibos con sus medios de pago.
 */
trait PreparaLibroDiario
{
    protected Sede $sede;

    /** Crea los permisos del módulo y la sede principal. */
    protected function prepararLibroDiario(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'fin_libroDiario', 'fin_ldRegistroCrear', 'fin_ldRegistroEditar', 'fin_ldRegistroAnular',
            'fin_ldAnularCerrado', 'fin_ldMedioPagoOtros', 'fin_ldTurnoCaja', 'fin_ldTurnos', 'fin_ldTurnoReversar', 'fin_ldCierreAprobar',
            'fin_ldTurnoAutorizar', 'fin_ldCatalogos', 'fin_ldCatalogoCrear', 'fin_ldCatalogoEditar',
            'fin_ldCatalogoInactivar',
        ] as $permiso) {
            Permission::create(['name' => $permiso, 'descripcion' => $permiso]);
        }

        $this->sede = Sede::factory()->create(['codigo_academico' => 'ACA', 'codigo_inventario' => 'INV']);
    }

    /**
     * Usuario con los permisos indicados y la sede asignada.
     *
     * @param  array<string>  $permisos
     */
    protected function usuarioCon(array $permisos, ?Sede $sede = null): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permisos);
        $usuario->sedes()->attach(($sede ?? $this->sede)->id);

        return $usuario;
    }

    /**
     * Recibo pagado con un solo medio de pago.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function reciboPagado(float $valor, string $medio = 'efectivo', array $attrs = []): ReciboPago
    {
        $recibo = ReciboPago::factory()->create(array_merge([
            'origen' => ReciboPago::ORIGEN_ACADEMICO,
            'sede_id' => $this->sede->id,
            'fecha_recibo' => today(),
            'valor_total' => $valor,
            'status' => ReciboPago::STATUS_CREADO,
        ], $attrs));

        ReciboPagoMedioPago::create([
            'recibo_pago_id' => $recibo->id,
            'medio_pago' => $medio,
            'valor' => $valor,
        ]);

        return $recibo;
    }
}
