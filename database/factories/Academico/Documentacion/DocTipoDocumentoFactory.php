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
            'se_ata_fecha'           => false,
            'campo_fecha_referencia' => null,
            'prefijo_numero'         => Str::upper($this->faker->unique()->bothify('DOC###')),
            'status'                 => 1,
        ];
    }

    /**
     * Tipo de documento atado a la fecha de matrícula (contratos, pagarés).
     *
     * @return static
     */
    public function atadoAMatricula(): static
    {
        return $this->state([
            'entidad_type'           => Matricula::class,
            'se_ata_fecha'           => true,
            'campo_fecha_referencia' => 'fecha_matricula',
        ]);
    }

    /**
     * Tipo de documento sin vigencia atada a fecha (cartas, certificaciones).
     *
     * @return static
     */
    public function sinFecha(): static
    {
        return $this->state([
            'entidad_type'           => Matricula::class,
            'se_ata_fecha'           => false,
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
