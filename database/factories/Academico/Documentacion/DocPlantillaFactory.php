<?php

namespace Database\Factories\Academico\Documentacion;

use App\Models\Academico\Documentacion\DocPlantilla;
use App\Models\Academico\Documentacion\DocTipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Academico\Documentacion\DocPlantilla>
 */
class DocPlantillaFactory extends Factory
{
    protected $model = DocPlantilla::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_documento_id' => DocTipoDocumento::factory(),
            'nombre'            => 'Versión ' . $this->faker->unique()->numberBetween(1, 9999),
            'version'           => $this->faker->unique()->numberBetween(1, 9999),
            'contenido_html'    => '<p>Contenido de prueba</p>',
            'status'            => DocPlantilla::STATUS_EN_PROCESO,
            'fecha_inicio'      => null,
            'fecha_fin'         => null,
        ];
    }

    /**
     * Versión aprobada, pendiente de activar.
     *
     * @return static
     */
    public function aprobada(): static
    {
        return $this->state(['status' => DocPlantilla::STATUS_APROBADA]);
    }

    /**
     * Versión activa vigente desde la fecha indicada.
     *
     * @param string $fechaInicio
     * @return static
     */
    public function activa(string $fechaInicio = '2020-01-01'): static
    {
        return $this->state([
            'status'       => DocPlantilla::STATUS_ACTIVA,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => null,
        ]);
    }

    /**
     * Versión histórica ya cerrada, con su ventana de vigencia.
     *
     * @param string $fechaInicio
     * @param string $fechaFin
     * @return static
     */
    public function historica(string $fechaInicio, string $fechaFin): static
    {
        return $this->state([
            'status'       => DocPlantilla::STATUS_INACTIVA,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
        ]);
    }
}
