<?php

namespace Database\Factories\Financiero\ReciboPago;

use App\Models\Academico\Matricula;
use App\Models\Configuracion\Sede;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory ReciboPagoFactory
 *
 * Genera instancias de prueba del modelo ReciboPago.
 * Incluye estados para diferentes tipos de recibos y orígenes.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\ReciboPago\ReciboPago>
 */
class ReciboPagoFactory extends Factory
{
    /**
     * El nombre del modelo asociado a esta factory.
     *
     * @var string
     */
    protected $model = ReciboPago::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sede = Sede::inRandomOrder()->first() ?? Sede::factory()->create();
        $origen = fake()->randomElement([ReciboPago::ORIGEN_INVENTARIOS, ReciboPago::ORIGEN_ACADEMICO]);

        // Configurar códigos si no existen
        if (!$sede->codigo_academico) {
            $sede->codigo_academico = 'ACAD';
            $sede->save();
        }
        if (!$sede->codigo_inventario) {
            $sede->codigo_inventario = 'INV';
            $sede->save();
        }

        // NO generar número aquí, se generará automáticamente en el evento creating del modelo
        // para evitar duplicados y condiciones de carrera
        $prefijo = $origen === ReciboPago::ORIGEN_ACADEMICO
            ? $sede->codigo_academico
            : $sede->codigo_inventario;

        $valorTotal = fake()->randomFloat(2, 10000, 5000000);
        $descuentoTotal = fake()->randomFloat(2, 0, $valorTotal * 0.3);

        return [
            // numero_recibo, consecutivo y prefijo se generarán automáticamente en el evento creating
            'origen' => $origen,
            'fecha_recibo' => fake()->dateTimeBetween('-1 year', 'now'),
            'fecha_transaccion' => fake()->dateTimeBetween('-1 year', 'now'),
            'valor_total' => $valorTotal,
            'descuento_total' => $descuentoTotal,
            'banco' => fake()->optional()->randomElement(['Banco de Bogotá', 'Bancolombia', 'Davivienda', 'BBVA', 'Banco Popular']),
            'status' => ReciboPago::STATUS_EN_PROCESO,
            'cierre' => null,
            'sede_id' => $sede->id,
            'estudiante_id' => User::whereHas('roles', function ($q) {
                $q->where('name', 'alumno');
            })->inRandomOrder()->first()?->id,
            'cajero_id' => User::inRandomOrder()->first()?->id ?? User::factory()->create()->id,
            'matricula_id' => Matricula::inRandomOrder()->first()?->id,
        ];
    }

    /**
     * Indica que el recibo está en proceso.
     *
     * @return static
     */
    public function enProceso(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReciboPago::STATUS_EN_PROCESO,
        ]);
    }

    /**
     * Indica que el recibo está creado.
     *
     * @return static
     */
    public function creado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReciboPago::STATUS_CREADO,
        ]);
    }

    /**
     * Indica que el recibo está cerrado.
     *
     * @return static
     */
    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReciboPago::STATUS_CERRADO,
            'cierre' => fake()->numberBetween(1, 100),
        ]);
    }

    /**
     * Indica que el recibo está anulado.
     *
     * @return static
     */
    public function anulado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReciboPago::STATUS_ANULADO,
        ]);
    }

    /**
     * Indica que el recibo es de origen académico.
     *
     * @return static
     */
    public function academico(): static
    {
        return $this->state(function (array $attributes) {
            $sede = Sede::find($attributes['sede_id']) ?? Sede::inRandomOrder()->first() ?? Sede::factory()->create();

            if (!$sede->codigo_academico) {
                $sede->codigo_academico = 'ACAD' . $sede->id;
                $sede->save();
            }

            // El número se generará automáticamente en el evento creating del modelo
            return [
                'origen' => ReciboPago::ORIGEN_ACADEMICO,
            ];
        });
    }

    /**
     * Indica que el recibo es de origen inventario.
     *
     * @return static
     */
    public function inventario(): static
    {
        return $this->state(function (array $attributes) {
            $sede = Sede::find($attributes['sede_id']) ?? Sede::inRandomOrder()->first() ?? Sede::factory()->create();

            if (!$sede->codigo_inventario) {
                $sede->codigo_inventario = 'INV' . $sede->id;
                $sede->save();
            }

            // El número se generará automáticamente en el evento creating del modelo
            return [
                'origen' => ReciboPago::ORIGEN_INVENTARIOS,
            ];
        });
    }
}

