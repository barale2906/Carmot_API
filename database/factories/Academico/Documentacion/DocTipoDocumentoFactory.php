<?php

namespace Database\Factories\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocTipoDocumento;
use App\Models\Academico\Matricula;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Documentacion\DocTipoDocumento>
 */
class DocTipoDocumentoFactory extends Factory
{
    protected $model = DocTipoDocumento::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo'                 => Str::upper($this->faker->unique()->bothify('TIPO-###')),
            'nombre'                 => 'Documento ' . $this->faker->unique()->word(),
            'descripcion'            => $this->faker->optional()->sentence(),
            'entidad_type'           => null,
            'conforma_matricula'     => false,
            'campo_fecha_referencia' => null,
            'status'                 => 1,
        ];
    }

    /**
     * Tipo que conforma la matrícula (contrato, pagaré, hoja de matrícula).
     *
     * @return static
     */
    public function conformaMatricula(): static
    {
        return $this->state([
            'entidad_type'           => Matricula::class,
            'conforma_matricula'     => true,
            'campo_fecha_referencia' => 'fecha_matricula',
        ]);
    }

    /**
     * Tipo que no conforma la matrícula: usa la plantilla vigente hoy.
     *
     * @return static
     */
    public function sinFecha(): static
    {
        return $this->state([
            'entidad_type'           => Matricula::class,
            'conforma_matricula'     => false,
            'campo_fecha_referencia' => null,
        ]);
    }

    /**
     * Tipo de documento inactivo.
     *
     * @return static
     */
    public function inactivo(): static
    {
        return $this->state(['status' => 0]);
    }
}
