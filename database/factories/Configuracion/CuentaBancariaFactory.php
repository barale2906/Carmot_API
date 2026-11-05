<?php

namespace Database\Factories\Configuracion;

use App\Models\Configuracion\Banco;
use App\Models\Configuracion\CuentaBancaria;
use App\Models\Configuracion\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto la cuenta queda asociada a una sede nueva; usar paraSedes() o
 * paraPoblacion() para definir dónde aplica.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\CuentaBancaria>
 */
class CuentaBancariaFactory extends Factory
{
    protected $model = CuentaBancaria::class;

    public function definition(): array
    {
        return [
            'poblacion_id' => null,
            'banco_id' => Banco::factory(),
            'tipo_cuenta' => fake()->randomElement(array_keys(CuentaBancaria::TIPOS)),
            'numero_cuenta' => fake()->unique()->numerify('###-######-##'),
            'titular' => fake()->company(),
            'descripcion' => null,
            'status' => 1,
        ];
    }

    /**
     * Las sedes elegidas con paraSedes() viajan como relación transitoria (no es columna)
     * desde afterMaking hasta afterCreating, donde se sincronizan en la tabla pivote.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CuentaBancaria $cuenta) {
            $sedes = $cuenta->relationLoaded('sedesPendientes')
                ? $cuenta->getRelation('sedesPendientes')
                : ($cuenta->poblacion_id ? [] : [Sede::factory()->create()->id]);
            $cuenta->unsetRelation('sedesPendientes');
            $cuenta->sedes()->sync($sedes);
        });
    }

    /**
     * Asocia la cuenta a las sedes indicadas.
     *
     * @param  array<int|Sede>|int|Sede  $sedes
     */
    public function paraSedes(array|int|Sede $sedes): static
    {
        $ids = collect(is_array($sedes) ? $sedes : [$sedes])
            ->map(fn ($s) => $s instanceof Sede ? $s->id : (int) $s)->all();

        return $this->afterMaking(fn (CuentaBancaria $cuenta) => $cuenta->setRelation('sedesPendientes', $ids));
    }

    /**
     * La cuenta aplica a todas las sedes de una ciudad (sin sedes explícitas).
     */
    public function paraPoblacion(int $poblacionId): static
    {
        return $this->state(['poblacion_id' => $poblacionId]);
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 0]);
    }
}
