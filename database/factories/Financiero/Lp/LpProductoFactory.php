<?php

namespace Database\Factories\Financiero\Lp;

use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpTipoProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear productos de listas de precios.
 * Las referencias académicas (cursos/módulos) se vinculan
 * a través de LpProductoReferencia, no de columnas en esta tabla.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Lp\LpProducto>
 */
class LpProductoFactory extends Factory
{
    /**
     * Modelo al que pertenece esta factory.
     *
     * @var class-string<\App\Models\Financiero\Lp\LpProducto>
     */
    protected $model = LpProducto::class;

    /**
     * Define el estado por defecto del modelo.
     * Genera un producto con tipo aleatorio, nombre de muestra, código único
     * y status aleatorio. No incluye referencias académicas; estas se gestionan
     * por separado a través de LpProductoReferencia.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipoProducto = LpTipoProducto::inRandomOrder()->first()
            ?? LpTipoProducto::factory()->activo()->create();

        $nombres = [
            'Curso de Programación Web',
            'Curso de Desarrollo de Software',
            'Módulo de Base de Datos',
            'Módulo de Frontend',
            'Módulo de Backend',
            'Certificado de Estudios',
            'Material Didáctico',
            'Kit de Herramientas',
            'Registro de Notas',
            'Diploma de Grado',
        ];

        return [
            'tipo_producto_id' => $tipoProducto->id,
            'nombre'           => fake()->randomElement($nombres),
            'codigo'           => fake()->unique()->regexify('[A-Z]{3,5}-[0-9]{3,5}'),
            'descripcion'      => fake()->optional(0.6)->paragraph(2),
            'status'           => fake()->randomElement([0, 1]),
        ];
    }

    /**
     * Estado para crear un producto con status activo (status = 1).
     *
     * @return static
     */
    public function activo(): static
    {
        return $this->state(['status' => 1]);
    }

    /**
     * Estado para crear un producto con status inactivo (status = 0).
     *
     * @return static
     */
    public function inactivo(): static
    {
        return $this->state(['status' => 0]);
    }

    /**
     * Estado para crear un producto asociado al tipo 'curso'.
     * Si el tipo no existe en la BD, lo crea mediante su propia factory.
     * No vincula automáticamente a ningún Curso; usar LpProductoReferencia para eso.
     *
     * @return static
     */
    public function curso(): static
    {
        return $this->state(function () {
            $tipo = LpTipoProducto::where('codigo', 'curso')->first()
                ?? LpTipoProducto::factory()->curso()->activo()->create();

            return ['tipo_producto_id' => $tipo->id];
        });
    }

    /**
     * Estado para crear un producto asociado al tipo 'modulo'.
     * Si el tipo no existe en la BD, lo crea mediante su propia factory.
     * No vincula automáticamente a ningún Modulo; usar LpProductoReferencia para eso.
     *
     * @return static
     */
    public function modulo(): static
    {
        return $this->state(function () {
            $tipo = LpTipoProducto::where('codigo', 'modulo')->first()
                ?? LpTipoProducto::factory()->modulo()->activo()->create();

            return ['tipo_producto_id' => $tipo->id];
        });
    }

    /**
     * Estado para crear un producto complementario (diploma, certificado, etc.).
     * Los productos complementarios no tienen referencias académicas.
     * Si el tipo no existe en la BD, lo crea mediante su propia factory.
     *
     * @return static
     */
    public function complementario(): static
    {
        return $this->state(function () {
            $tipo = LpTipoProducto::where('codigo', 'complementario')->first()
                ?? LpTipoProducto::factory()->complementario()->activo()->create();

            return ['tipo_producto_id' => $tipo->id];
        });
    }
}
