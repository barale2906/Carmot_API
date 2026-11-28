<?php

namespace App\Http\Requests\Api\Traslados;

use App\Http\Requests\Api\Academico\Concerns\ValidaCicloDeMatricula;
use App\Http\Requests\Api\Academico\Concerns\ValidaProgramacionDeMatricula;
use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Services\Traslados\TrasladoMatriculaService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos y reglas de un traslado: a qué sede, curso, ciclo y programación pasa la matrícula. Es la
 * base de la simulación y del registro del traslado (StoreTrasladoRequest).
 *
 * Las validaciones que consultan la base de datos viven en `withValidator`. El tipo de traslado no
 * se envía: se deduce de comparar el destino con el origen.
 */
class SimularTrasladoRequest extends FormRequest
{
    use ValidaCicloDeMatricula, ValidaProgramacionDeMatricula;

    /** Estados académicos desde los que se puede trasladar una matrícula vigente. */
    private const ESTADOS_TRASLADABLES = [
        Matricula::ESTADO_ACTIVO,
        Matricula::ESTADO_APLAZADO,
        Matricula::ESTADO_PENDIENTE_INICIO,
    ];

    private ?Matricula $matricula = null;

    private ?int $programacionResuelta = null;

    /**
     * La autorización se hace con el middleware de permisos de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación de la petición.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'matricula_id' => ['required', 'integer', 'exists:matriculas,id'],
            'sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'ciclo_id' => ['required', 'integer', 'exists:ciclos,id'],
            'programacion_id' => ['nullable', 'integer', 'exists:programacions,id'],
            'lp_precio_producto_id' => ['nullable', 'integer', 'exists:lp_precios_producto,id'],
            'fecha' => ['nullable', 'date'],
            'cuotas' => ['nullable', 'array', 'min:1'],
            'cuotas.*.valor' => ['required', 'numeric', 'gt:0'],
            'cuotas.*.fecha_vencimiento' => ['required', 'date'],
            'condonar_costo' => ['sometimes', 'boolean'],
            'recalcular_descuentos' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'matricula_id.required' => 'Indique la matrícula a trasladar.',
            'matricula_id.exists' => 'La matrícula seleccionada no existe.',
            'sede_id.required' => 'La sede de destino es obligatoria.',
            'sede_id.exists' => 'La sede de destino no existe.',
            'curso_id.required' => 'El curso de destino es obligatorio.',
            'curso_id.exists' => 'El curso de destino no existe.',
            'ciclo_id.required' => 'El ciclo de destino es obligatorio.',
            'ciclo_id.exists' => 'El ciclo de destino no existe.',
            'programacion_id.exists' => 'La programación de destino no existe.',
            'lp_precio_producto_id.exists' => 'El precio de lista seleccionado no existe.',
            'fecha.date' => 'La fecha del traslado no es válida.',
            'cuotas.min' => 'El plan de pagos debe tener al menos una cuota.',
            'cuotas.*.valor.required' => 'Cada cuota del plan de pagos necesita un valor.',
            'cuotas.*.valor.gt' => 'El valor de cada cuota debe ser mayor a cero.',
            'cuotas.*.fecha_vencimiento.required' => 'Cada cuota del plan de pagos necesita una fecha de vencimiento.',
        ];
    }

    /**
     * Nombres de los campos para los mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'matricula_id' => 'matrícula',
            'sede_id' => 'sede',
            'curso_id' => 'curso',
            'ciclo_id' => 'ciclo',
            'programacion_id' => 'programación',
            'lp_precio_producto_id' => 'precio de lista',
            'fecha' => 'fecha del traslado',
            'cuotas' => 'plan de pagos',
        ];
    }

    /**
     * Validaciones que dependen de la matrícula y del destino.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $matricula = $this->matricula();
            $ciclo = Ciclo::findOrFail($this->input('ciclo_id'));
            $cambiaSede = (int) $this->input('sede_id') !== (int) $matricula->sede_id;
            $cambiaCurso = (int) $this->input('curso_id') !== (int) $matricula->curso_id;

            $this->validarMatricula($validator, $matricula);
            $this->validarAccesoASedes($validator, $matricula);
            $this->validarCicloDeMatricula($validator, $ciclo->id, $this->input('sede_id'), $this->input('curso_id'));
            $this->validarEstadoDelCiclo($validator, $ciclo);
            $this->validarProgramacion($validator, $ciclo);
            $this->validarDestinoDistinto($validator, $matricula);
            $this->validarCursoSinMatriculaVigente($validator, $matricula, $cambiaCurso);
            $this->validarPrecio($validator, $matricula, $cambiaSede || $cambiaCurso);
            $this->validarFecha($validator, $matricula, $ciclo);
            $this->validarPagoAcordado($validator, $cambiaSede || $cambiaCurso);
            $this->validarRecalculoDescuentos($validator, $matricula);
        });
    }

    /**
     * Matrícula que se traslada.
     */
    public function matricula(): Matricula
    {
        return $this->matricula ??= Matricula::findOrFail($this->input('matricula_id'));
    }

    /**
     * Destino ya normalizado y con la programación resuelta, listo para el servicio de traslados.
     *
     * @return array<string, mixed>
     */
    public function destino(): array
    {
        return [
            'sede_id' => (int) $this->input('sede_id'),
            'curso_id' => (int) $this->input('curso_id'),
            'ciclo_id' => (int) $this->input('ciclo_id'),
            'programacion_id' => $this->programacionResuelta,
            'lp_precio_producto_id' => $this->filled('lp_precio_producto_id') ? (int) $this->input('lp_precio_producto_id') : null,
            'fecha' => $this->input('fecha', today()->toDateString()),
            'cuotas' => $this->input('cuotas'),
            'condonar_costo' => $this->boolean('condonar_costo'),
            'recalcular_descuentos' => $this->boolean('recalcular_descuentos'),
        ];
    }

    /**
     * Solo se traslada una matrícula vigente y en un estado académico que siga cursando.
     */
    private function validarMatricula($validator, Matricula $matricula): void
    {
        if ($matricula->status !== 1 || ! in_array($matricula->estado_academico, self::ESTADOS_TRASLADABLES, true)) {
            $validator->errors()->add('matricula_id', 'Solo se puede trasladar una matrícula vigente que esté activa, aplazada o pendiente por iniciar.');
        }
    }

    /**
     * Quien traslada debe tener asignada la sede de origen o la de destino.
     */
    private function validarAccesoASedes($validator, Matricula $matricula): void
    {
        $usuario = $this->user();

        if (! $usuario->tieneAccesoSede((int) $matricula->sede_id) && ! $usuario->tieneAccesoSede((int) $this->input('sede_id'))) {
            $validator->errors()->add('sede_id', 'No tiene asignada la sede de origen ni la de destino de este traslado.');
        }
    }

    /**
     * El ciclo de destino debe estar activo, no haber terminado y tener las notas abiertas.
     */
    private function validarEstadoDelCiclo($validator, Ciclo $ciclo): void
    {
        if ($validator->errors()->has('ciclo_id')) {
            return;
        }

        if ($ciclo->status !== 1) {
            $validator->errors()->add('ciclo_id', 'El ciclo de destino no está activo.');
        } elseif ($ciclo->fecha_fin && $ciclo->fecha_fin->lt(today())) {
            $validator->errors()->add('ciclo_id', 'El ciclo de destino ya finalizó.');
        } elseif ($ciclo->notasCerradas()) {
            $validator->errors()->add('ciclo_id', 'El ciclo de destino tiene las notas cerradas.');
        }
    }

    /**
     * La programación elegida debe ser de la sede y el curso de destino. Si no se envía, se toma la
     * que corresponde al ciclo; si hay varias posibles debe elegirse una.
     */
    private function validarProgramacion($validator, Ciclo $ciclo): void
    {
        if ($this->filled('programacion_id')) {
            $this->validarProgramacionDeMatricula($validator, $this->input('programacion_id'), $this->input('sede_id'), $this->input('curso_id'));
            $this->programacionResuelta = (int) $this->input('programacion_id');

            return;
        }

        if ($validator->errors()->has('ciclo_id')) {
            return;
        }

        $candidatas = app(TrasladoMatriculaService::class)->programacionesDelCiclo($ciclo);

        if ($candidatas->count() > 1) {
            $validator->errors()->add('programacion_id', 'El ciclo de destino tiene varias programaciones posibles: seleccione una.');
        }

        $this->programacionResuelta = $candidatas->count() === 1 ? $candidatas->first()->id : null;
    }

    /**
     * El destino debe cambiar algo respecto al origen.
     */
    private function validarDestinoDistinto($validator, Matricula $matricula): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $igual = (int) $this->input('sede_id') === (int) $matricula->sede_id
            && (int) $this->input('curso_id') === (int) $matricula->curso_id
            && (int) $this->input('ciclo_id') === (int) $matricula->ciclo_id
            && $this->programacionResuelta === ($matricula->programacion_id ? (int) $matricula->programacion_id : null);

        if ($igual) {
            $validator->errors()->add('ciclo_id', 'El destino es igual al origen: cambie la sede, el curso, el ciclo o la programación.');
        }
    }

    /**
     * Al cambiar de curso, el estudiante no puede tener ya una matrícula vigente en el curso nuevo.
     */
    private function validarCursoSinMatriculaVigente($validator, Matricula $matricula, bool $cambiaCurso): void
    {
        if (! $cambiaCurso) {
            return;
        }

        $tieneOtra = Matricula::byEstudiante($matricula->estudiante_id)
            ->byCurso($this->input('curso_id'))
            ->vigentes()
            ->where('id', '!=', $matricula->id)
            ->exists();

        if ($tieneOtra) {
            $validator->errors()->add('curso_id', 'El estudiante ya tiene una matrícula vigente en este curso.');
        }
    }

    /**
     * Al cambiar de sede o de curso el precio cambia: se debe elegir un precio de lista vigente
     * para el curso en la población de la sede de destino. Las matrículas sin cartera ni precio de
     * lista (anteriores al módulo financiero) no lo necesitan.
     */
    private function validarPrecio($validator, Matricula $matricula, bool $cambiaSedeOCurso): void
    {
        if (! $cambiaSedeOCurso) {
            return;
        }

        $manejaCartera = $matricula->lp_precio_producto_id || $matricula->carteras()->exists();

        if (! $this->filled('lp_precio_producto_id')) {
            if ($manejaCartera) {
                $validator->errors()->add('lp_precio_producto_id', 'Seleccione el precio de lista del curso en la sede de destino.');
            }

            return;
        }

        $valido = app(TrasladoMatriculaService::class)
            ->preciosDisponibles((int) $this->input('sede_id'), (int) $this->input('curso_id'))
            ->whereKey($this->input('lp_precio_producto_id'))
            ->exists();

        if (! $valido) {
            $validator->errors()->add('lp_precio_producto_id', 'El precio de lista no corresponde al curso ni a la sede de destino, o su lista no está vigente.');
        }
    }

    /**
     * La fecha del traslado no puede ser anterior a la matrícula ni posterior al fin del ciclo de destino.
     */
    private function validarFecha($validator, Matricula $matricula, Ciclo $ciclo): void
    {
        if (! $this->filled('fecha')) {
            return;
        }

        $fecha = Carbon::parse($this->input('fecha'));

        if ($fecha->lt($matricula->fecha_matricula)) {
            $validator->errors()->add('fecha', 'La fecha del traslado no puede ser anterior a la fecha de la matrícula.');
        } elseif ($ciclo->fecha_fin && $fecha->gt($ciclo->fecha_fin)) {
            $validator->errors()->add('fecha', 'La fecha del traslado no puede ser posterior al fin del ciclo de destino.');
        }
    }

    /**
     * Acordar el plan de pagos y condonar el cobro del traslado requiere un permiso especial, y el
     * plan solo tiene sentido cuando cambia la cartera (cambio de sede o de curso).
     */
    private function validarPagoAcordado($validator, bool $cambiaSedeOCurso): void
    {
        $puedeAcordar = $this->user()->can('tra_trasladoPagoAcordado');

        if ($this->filled('cuotas')) {
            if (! $puedeAcordar) {
                $validator->errors()->add('cuotas', 'No tiene permiso para acordar el plan de pagos del traslado.');
            } elseif (! $cambiaSedeOCurso) {
                $validator->errors()->add('cuotas', 'El plan de pagos solo aplica al cambiar de sede o de curso.');
            }
        }

        if ($this->boolean('condonar_costo') && ! $puedeAcordar) {
            $validator->errors()->add('condonar_costo', 'No tiene permiso para condonar el cobro del traslado.');
        }
    }

    /**
     * Recalcular los descuentos reemplaza los congelados: no se permite si a la cuota 0 ya se le
     * aplicó un descuento, porque quedaría un descuento pagado sin respaldo.
     */
    private function validarRecalculoDescuentos($validator, Matricula $matricula): void
    {
        if ($this->boolean('recalcular_descuentos')
            && $matricula->carteras()->where('numero_cuota', 0)->where('descuento', '>', 0)->exists()) {
            $validator->errors()->add('recalcular_descuentos', 'No se pueden recalcular los descuentos: la matrícula ya recibió un descuento en su pago.');
        }
    }
}
