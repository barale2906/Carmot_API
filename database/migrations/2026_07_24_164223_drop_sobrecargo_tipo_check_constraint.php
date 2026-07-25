<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina el CHECK constraint que forzaba a los sobrecargos a ser solo porcentuales.
 * Ahora los sobrecargos pueden ser tipo 'porcentual' o 'valor_fijo', igual que los descuentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE descuentos DROP CHECK chk_descuentos_sobrecargo_tipo');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE descuentos ADD CONSTRAINT chk_descuentos_sobrecargo_tipo CHECK (tipo_movimiento != 'sobrecargo' OR tipo = 'porcentual')");
    }
};
