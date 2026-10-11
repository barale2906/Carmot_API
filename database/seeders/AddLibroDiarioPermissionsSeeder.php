<?php

namespace Database\Seeders;

use App\Models\Financiero\LibroDiario\LdImpuesto;
use App\Models\Financiero\LibroDiario\LdTipoMovimiento;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Agrega los permisos del módulo Libro Diario (turnos de caja, movimientos,
 * consignaciones, catálogos) y los catálogos base de tipos de movimiento e impuestos.
 * Seguro de correr múltiples veces (usa firstOrCreate).
 *
 * Ejecutar con: php artisan db:seed --class=AddLibroDiarioPermissionsSeeder
 */
class AddLibroDiarioPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $superusuario = Role::findByName('superusuario');
        $financiero = Role::findByName('financiero');
        $coordinador = Role::findByName('coordinador');
        $auxiliar = Role::findByName('auxiliar');
        $cajero = Role::findByName('cajero');

        // Operación de caja: quien maneja dinero
        $operacion = [$superusuario, $financiero, $coordinador, $auxiliar, $cajero];
        // Supervisión: ver y cerrar turnos de otros, anular
        $supervision = [$superusuario, $financiero, $coordinador];

        $permisos = [
            'fin_libroDiario' => ['ver libro diario, movimientos, consignaciones y soportes', $operacion],
            'fin_ldRegistroCrear' => ['registrar movimientos, consignaciones y soportes', $operacion],
            'fin_ldRegistroEditar' => ['editar movimientos y consignaciones de un turno abierto', $operacion],
            'fin_ldRegistroAnular' => ['anular movimientos y consignaciones de un turno abierto', $supervision],
            'fin_ldAnularCerrado' => ['anular registros de un turno de caja ya cerrado', [$superusuario, $financiero]],
            'fin_ldMedioPagoOtros' => ['registrar movimientos con medios de pago distintos a efectivo', [$superusuario, $financiero]],
            'fin_ldTurnoCaja' => ['abrir y cerrar el propio turno de caja', $operacion],
            'fin_ldTurnos' => ['ver y cerrar turnos de caja de sus sedes', $supervision],
            'fin_ldCierreAprobar' => ['aprobar o rechazar cierres de caja y ver el arqueo', $supervision],
            'fin_ldTurnoReversar' => ['reversar el cierre de un turno de caja', [$superusuario, $financiero]],
            'fin_ldTurnoAutorizar' => ['autorizar a un cajero a abrir otro turno el mismo día', $supervision],
            'fin_ldCatalogos' => ['ver tipos de movimiento e impuestos', $supervision],
            'fin_ldCatalogoCrear' => ['crear tipos de movimiento e impuestos', [$superusuario, $financiero]],
            'fin_ldCatalogoEditar' => ['editar tipos de movimiento e impuestos', [$superusuario, $financiero]],
            'fin_ldCatalogoInactivar' => ['eliminar tipos de movimiento e impuestos', [$superusuario]],
        ];

        foreach ($permisos as $nombre => [$descripcion, $roles]) {
            Permission::firstOrCreate(['name' => $nombre], ['descripcion' => $descripcion])->syncRoles($roles);
        }

        $this->sembrarCatalogos();

        $this->command?->info('Permisos y catálogos del libro diario aplicados correctamente.');
    }

    /**
     * Tipos de movimiento e impuestos base (Colombia). Se pueden ajustar luego desde el módulo.
     */
    private function sembrarCatalogos(): void
    {
        $egresos = ['Arriendo', 'Servicios públicos', 'Nómina y honorarios', 'Papelería y útiles',
            'Aseo y cafetería', 'Mantenimiento', 'Proveedores', 'Transporte', 'Publicidad', 'Otros egresos'];
        $ingresos = ['Alquiler de espacios', 'Venta de certificados', 'Otros ingresos'];

        foreach ($egresos as $nombre) {
            LdTipoMovimiento::firstOrCreate(['nombre' => $nombre], ['clase' => LdTipoMovimiento::CLASE_EGRESO, 'status' => 1]);
        }
        foreach ($ingresos as $nombre) {
            LdTipoMovimiento::firstOrCreate(['nombre' => $nombre], ['clase' => LdTipoMovimiento::CLASE_INGRESO, 'status' => 1]);
        }

        $impuestos = [
            ['IVA 19 %', 19, LdImpuesto::EFECTO_SUMA],
            ['IVA 5 %', 5, LdImpuesto::EFECTO_SUMA],
            ['Impoconsumo 8 %', 8, LdImpuesto::EFECTO_SUMA],
            ['Retención en la fuente servicios 4 %', 4, LdImpuesto::EFECTO_RESTA],
            ['Retención en la fuente compras 2,5 %', 2.5, LdImpuesto::EFECTO_RESTA],
            ['Retención en la fuente honorarios 10 %', 10, LdImpuesto::EFECTO_RESTA],
            ['ReteIVA 15 % del IVA (2,85 %)', 2.85, LdImpuesto::EFECTO_RESTA],
            ['ReteICA', 0.966, LdImpuesto::EFECTO_RESTA],
        ];

        foreach ($impuestos as [$nombre, $porcentaje, $efecto]) {
            LdImpuesto::firstOrCreate(['nombre' => $nombre], ['porcentaje' => $porcentaje, 'efecto' => $efecto, 'status' => 1]);
        }
    }
}
