<?php

namespace Database\Factories\Financiero\Descuento;

use App\Models\Financiero\Descuento\Descuento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory DescuentoFactory
 *
 * Genera instancias de prueba del modelo Descuento.
 * Incluye estados para diferentes tipos de descuentos.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Financiero\Descuento\Descuento>
 */
class DescuentoFactory extends Factory
{
    /**
     * El nombre del modelo asociado a esta factory.
     *
     * @var string
     */
    protected $model = Descuento::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio = fake()->dateTimeBetween('-1 month', '+1 month');
        $fechaFin = fake()->dateTimeBetween($fechaInicio, '+6 months');

        // Generar código de descuento opcional (50% de probabilidad)
        $codigoDescuento = fake()->boolean(50) ? fake()->unique()->regexify('[A-Z0-9]{8,15}') : null;

        // Seleccionar tipo de activación primero
        $tipoActivacion = fake()->randomElement([
            Descuento::ACTIVACION_PAGO_ANTICIPADO,
            Descuento::ACTIVACION_PROMOCION_MATRICULA,
            Descuento::ACTIVACION_CODIGO_PROMOCIONAL
        ]);

        // Si es pago anticipado, siempre debe tener dias_anticipacion
        // Si es código promocional, debe tener código_descuento
        $diasAnticipacion = null;
        if ($tipoActivacion === Descuento::ACTIVACION_PAGO_ANTICIPADO) {
            $diasAnticipacion = fake()->numberBetween(1, 30);
            $codigoDescuento = null; // No puede tener código si es pago anticipado
        } elseif ($tipoActivacion === Descuento::ACTIVACION_CODIGO_PROMOCIONAL) {
            $codigoDescuento = $codigoDescuento ?? fake()->unique()->regexify('[A-Z0-9]{8,15}');
            $diasAnticipacion = null;
        }

        return [
            'nombre' => fake()->sentence(3),
            'codigo_descuento' => $codigoDescuento,
            'descripcion' => fake()->optional()->paragraph(),
            'tipo' => fake()->randomElement([
                Descuento::TIPO_PORCENTUAL,
                Descuento::TIPO_VALOR_FIJO
            ]),
            'valor' => fake()->randomFloat(2, 1, 50),
            'aplicacion' => fake()->randomElement([
                Descuento::APLICACION_VALOR_TOTAL,
                Descuento::APLICACION_MATRICULA,
                Descuento::APLICACION_CUOTA
            ]),
            'tipo_activacion' => $tipoActivacion,
            'dias_anticipacion' => $diasAnticipacion,
            'permite_acumulacion' => fake()->boolean(30), // 30% de probabilidad de ser true
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'status' => Descuento::STATUS_EN_PROCESO,
        ];
    }

    /**
     * Indica que el descuento es porcentual.
     *
     * @return static
     */
    public function porcentual(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => Descuento::TIPO_PORCENTUAL,
            'valor' => fake()->randomFloat(2, 1, 50),
        ]);
    }

    /**
     * Indica que el descuento es de valor fijo.
     *
     * @return static
     */
    public function valorFijo(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => Descuento::TIPO_VALOR_FIJO,
            'valor' => fake()->randomFloat(2, 1000, 100000),
        ]);
    }

    /**
     * Indica que el descuento aplica al valor total.
     *
     * @return static
     */
    public function aplicaValorTotal(): static
    {
        return $this->state(fn (array $attributes) => [
            'aplicacion' => Descuento::APLICACION_VALOR_TOTAL,
        ]);
    }

    /**
     * Indica que el descuento aplica a la matrícula.
     *
     * @return static
     */
    public function aplicaMatricula(): static
    {
        return $this->state(fn (array $attributes) => [
            'aplicacion' => Descuento::APLICACION_MATRICULA,
        ]);
    }

    /**
     * Indica que el descuento aplica a las cuotas.
     *
     * @return static
     */
    public function aplicaCuota(): static
    {
        return $this->state(fn (array $attributes) => [
            'aplicacion' => Descuento::APLICACION_CUOTA,
        ]);
    }

    /**
     * Indica que el descuento se activa por pago anticipado.
     *
     * @return static
     */
    public function pagoAnticipado(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_activacion' => Descuento::ACTIVACION_PAGO_ANTICIPADO,
            'dias_anticipacion' => fake()->numberBetween(1, 30),
            'codigo_descuento' => null,
        ]);
    }

    /**
     * Indica que el descuento es una promoción de matrícula.
     *
     * @return static
     */
    public function promocionMatricula(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_activacion' => Descuento::ACTIVACION_PROMOCION_MATRICULA,
            'dias_anticipacion' => null,
            'codigo_descuento' => null,
        ]);
    }

    /**
     * Indica que el descuento se activa por código promocional.
     *
     * @return static
     */
    public function codigoPromocional(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_activacion' => Descuento::ACTIVACION_CODIGO_PROMOCIONAL,
            'codigo_descuento' => fake()->unique()->regexify('[A-Z0-9]{8,15}'),
            'dias_anticipacion' => null,
        ]);
    }

    /**
     * Indica que el descuento permite acumulación.
     *
     * @return static
     */
    public function acumulable(): static
    {
        return $this->state(fn (array $attributes) => [
            'permite_acumulacion' => true,
        ]);
    }

    /**
     * Indica que el descuento no permite acumulación.
     *
     * @return static
     */
    public function noAcumulable(): static
    {
        return $this->state(fn (array $attributes) => [
            'permite_acumulacion' => false,
        ]);
    }

    /**
     * Indica que el descuento está en proceso.
     *
     * @return static
     */
    public function enProceso(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Descuento::STATUS_EN_PROCESO,
        ]);
    }

    /**
     * Indica que el descuento está aprobado.
     *
     * @return static
     */
    public function aprobado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Descuento::STATUS_APROBADO,
        ]);
    }

    /**
     * Indica que el descuento está activo.
     *
     * @return static
     */
    public function activo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Descuento::STATUS_ACTIVO,
        ]);
    }

    /**
     * Indica que el descuento está inactivo.
     *
     * @return static
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Descuento::STATUS_INACTIVO,
        ]);
    }

    /**
     * Indica que el descuento está vigente (activo y dentro del rango de fechas).
     *
     * @return static
     */
    public function vigente(): static
    {
        return $this->state(function (array $attributes) {
            $fechaInicio = now()->subDays(7);
            $fechaFin = now()->addDays(30);

            return [
                'status' => Descuento::STATUS_ACTIVO,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ];
        });
    }
}

