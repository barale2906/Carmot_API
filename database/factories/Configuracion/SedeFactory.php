<?php

namespace Database\Factories\Configuracion;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuracion\Sede>
 */
class SedeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ciudades específicas de Colombia para las sedes
        $ciudadesColombia = ['Tunja', 'Duitama', 'Ipiales'];

        return [
            'nombre' => $this->faker->company() . ' - Sede',
            'direccion' => $this->faker->streetAddress(),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'hora_inicio' => $this->faker->time('H:i:s', '08:00:00'),
            'hora_fin' => $this->faker->time('H:i:s', '18:00:00'),
            'poblacion_id' => \App\Models\Configuracion\Poblacion::whereIn('nombre', $ciudadesColombia)
                ->where('pais', 'Colombia')
                ->inRandomOrder()
                ->first()
                ?->id ?? \App\Models\Configuracion\Poblacion::factory(),
        ];
    }
}
