<?php

namespace Database\Factories\Financiero\Cartera;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear registros de cartera en pruebas.
 *
 * @extends Factory<Cartera>
 */
class CarteraFactory extends Factory
{
    protected $model = Cartera::class;

    /**
     * Estado por defecto: cuota activa con saldo pendiente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $valor = fake()->randomFloat(2, 50000, 500000);

        return [
            'matricula_id'     => Matricula::inRandomOrder()->first()?->id ?? Matricula::factory(),
            'sede_id'          => Sede::inRandomOrder()->first()?->id ?? 1,
            'estudiante_id'    => User::inRandomOrder()->first()?->id ?? User::factory(),
            'numero_cuota'     => fake()->numberBetween(0, 12),
            'valor'            => $valor,
            'saldo'            => $valor,
            'abono'            => 0,
            'descuento'        => 0,
            'fecha_vencimiento' => fake()->dateTimeBetween('-3 months', '+6 months')->format('Y-m-d'),
            'status'           => Cartera::getStatusKey('Activa'),
            'observaciones'    => null,
        ];
    }

    /**
     * Estado Activa (sin abono).
     */
    public function activa(): static
    {
        return $this->state(fn () => ['status' => Cartera::getStatusKey('Activa'), 'abono' => 0]);
    }

    /**
     * Estado Abonada (pago parcial).
     */
    public function abonada(): static
    {
        return $this->state(function (array $attrs) {
            $abono = $attrs['valor'] * 0.4;

            return [
                'status'  => Cartera::getStatusKey('Abonada'),
                'abono'   => $abono,
                'saldo'   => $attrs['valor'] - $abono,
            ];
        });
    }

    /**
     * Estado Cerrada (saldo = 0).
     */
    public function cerrada(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => Cartera::getStatusKey('Cerrada'),
            'abono'  => $attrs['valor'],
            'saldo'  => 0,
        ]);
    }

    /**
     * Cuota de matrícula (cuota 0).
     */
    public function cuotaMatricula(): static
    {
        return $this->state(fn () => ['numero_cuota' => 0]);
    }

    /**
     * Cuota vencida (fecha_vencimiento en el pasado).
     */
    public function vencida(): static
    {
        return $this->state(fn () => [
            'fecha_vencimiento' => fake()->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d'),
        ]);
    }
}
