<?php

namespace Database\Seeders;

use App\Models\Financiero\ConceptoPago\ConceptoPago;
use Illuminate\Database\Seeder;

/**
 * Agrega el concepto de pago "Traslado", cuyo valor es la tarifa que se cobra al trasladar a un
 * estudiante. Nace en 0 (no se cobra); se ajusta desde conceptos de pago. Si el concepto ya
 * existe no lo modifica, así que re-ejecutarlo no pisa una tarifa ya configurada.
 *
 * Ejecutar con: php artisan db:seed --class=AddConceptoTrasladoSeeder
 */
class AddConceptoTrasladoSeeder extends Seeder
{
    /**
     * Ejecuta el seeder del concepto.
     */
    public function run(): void
    {
        ConceptoPago::firstOrCreate(
            ['nombre' => ConceptoPago::TRASLADO],
            ['tipo' => 0, 'valor' => 0.00]
        );
    }
}
