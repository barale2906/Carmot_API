<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Referido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Crm\Agenda>
 */
class AgendaFactory extends Factory
{
    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referido_id'   => Referido::all()->random()->id,
            'agendador_id'  => User::all()->random()->id,
            'fecha'         => fake()->date(),
            'hora'          => fake()->time(),
            'jornada'       => fake()->randomElement(['am', 'pm']),
            'status'        => fake()->randomElement([0, 1, 2, 3, 4]),
        ];
    }

    /**
     * Indica que la agenda está eliminada suavemente.
     *
     * @return static
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indica que la agenda fue eliminada recientemente.
     *
     * @return static
     */
    public function recentlyDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indica que la agenda fue eliminada hace mucho tiempo.
     *
     * @return static
     */
    public function oldDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => fake()->dateTimeBetween('-1 year', '-6 months'),
        ]);
    }
}
