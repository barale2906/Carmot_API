<?php

namespace Database\Factories\Activos;

use App\Models\Activos\ActActivo;
use App\Models\Activos\ActCategoria;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<ActActivo>
 */
class ActActivoFactory extends Factory
{
    protected $model = ActActivo::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo'        => 'ACT-' . fake()->unique()->numerify('######'),
            'nombre'        => fake()->randomElement(['Taladro', 'Compresor', 'Computador portátil', 'Escritorio', 'Soldador MIG', 'Elevador hidráulico']),
            'categoria_id'  => ActCategoria::factory(),
            'sede_id'       => Sede::factory(),
            'fecha_ingreso' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'valor'         => fake()->randomFloat(2, 100000, 20000000),
            'estado'        => 'operativo',
        ];
    }
}
