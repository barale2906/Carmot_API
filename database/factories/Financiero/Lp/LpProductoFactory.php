<?php

namespace Database\Factories\Financiero\Lp;

use App\Models\Academico\Curso;
use App\Models\Academico\Modulo;
use App\Models\Financiero\Lp\LpProducto;
use App\Models\Financiero\Lp\LpTipoProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para crear productos de listas de precios.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Lp\LpProducto>
 */
class LpProductoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LpProducto::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipoProducto = LpTipoProducto::inRandomOrder()->first()
            ?? LpTipoProducto::factory()->activo()->create();

        // Determinar si debe tener referencia según el tipo
        $referenciaTipo = null;
        $referenciaId = null;

        if ($tipoProducto->codigo === 'curso') {
            $curso = Curso::inRandomOrder()->first();
            if ($curso) {
                $referenciaTipo = 'curso';
                $referenciaId = $curso->id;
            }
        } elseif ($tipoProducto->codigo === 'modulo') {
            $modulo = Modulo::inRandomOrder()->first();
            if ($modulo) {
                $referenciaTipo = 'modulo';
                $referenciaId = $modulo->id;
            }
        }

        $nombresProductos = [
            'Curso de Programación Web',
            'Curso de Desarrollo de Software',
            'Módulo de Base de Datos',
            'Módulo de Frontend',
            'Módulo de Backend',
            'Certificado de Estudios',
            'Material Didáctico',
            'Kit de Herramientas',
            'Curso Intensivo de Programación',
            'Módulo de Seguridad Informática',
        ];

        return [
            'tipo_producto_id' => $tipoProducto->id,
            'nombre' => fake()->randomElement($nombresProductos),
            'codigo' => fake()->unique()->regexify('[A-Z]{3,5}-[0-9]{3,5}'),
            'descripcion' => fake()->optional(0.6)->paragraph(2),
            'referencia_id' => $referenciaId,
            'referencia_tipo' => $referenciaTipo,
            'status' => fake()->randomElement([0, 1]), // 0: Inactivo, 1: Activo
        ];
    }

    /**
     * Estado para crear un producto activo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function activo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 1,
            ];
        });
    }

    /**
     * Estado para crear un producto inactivo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function inactivo(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    /**
     * Estado para crear un producto de tipo curso.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function curso(): static
    {
        return $this->state(function (array $attributes) {
            $tipoProducto = LpTipoProducto::where('codigo', 'curso')->first()
                ?? LpTipoProducto::factory()->curso()->activo()->create();

            $curso = Curso::inRandomOrder()->first();

            return [
                'tipo_producto_id' => $tipoProducto->id,
                'referencia_tipo' => $curso ? 'curso' : null,
                'referencia_id' => $curso ? $curso->id : null,
            ];
        });
    }

    /**
     * Estado para crear un producto de tipo módulo.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function modulo(): static
    {
        return $this->state(function (array $attributes) {
            $tipoProducto = LpTipoProducto::where('codigo', 'modulo')->first()
                ?? LpTipoProducto::factory()->modulo()->activo()->create();

            $modulo = Modulo::inRandomOrder()->first();

            return [
                'tipo_producto_id' => $tipoProducto->id,
                'referencia_tipo' => $modulo ? 'modulo' : null,
                'referencia_id' => $modulo ? $modulo->id : null,
            ];
        });
    }

    /**
     * Estado para crear un producto complementario.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function complementario(): static
    {
        return $this->state(function (array $attributes) {
            $tipoProducto = LpTipoProducto::where('codigo', 'complementario')->first()
                ?? LpTipoProducto::factory()->complementario()->activo()->create();

            return [
                'tipo_producto_id' => $tipoProducto->id,
                'referencia_tipo' => null,
                'referencia_id' => null,
            ];
        });
    }

    /**
     * Estado para crear un producto con referencia a un curso específico.
     *
     * @param int $cursoId ID del curso
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conCurso(int $cursoId): static
    {
        return $this->state(function (array $attributes) use ($cursoId) {
            $tipoProducto = LpTipoProducto::where('codigo', 'curso')->first()
                ?? LpTipoProducto::factory()->curso()->activo()->create();

            return [
                'tipo_producto_id' => $tipoProducto->id,
                'referencia_tipo' => 'curso',
                'referencia_id' => $cursoId,
            ];
        });
    }

    /**
     * Estado para crear un producto con referencia a un módulo específico.
     *
     * @param int $moduloId ID del módulo
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function conModulo(int $moduloId): static
    {
        return $this->state(function (array $attributes) use ($moduloId) {
            $tipoProducto = LpTipoProducto::where('codigo', 'modulo')->first()
                ?? LpTipoProducto::factory()->modulo()->activo()->create();

            return [
                'tipo_producto_id' => $tipoProducto->id,
                'referencia_tipo' => 'modulo',
                'referencia_id' => $moduloId,
            ];
        });
    }
}




