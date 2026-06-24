<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Resource de precarga de matrícula.
 *
 * Expone exclusivamente los datos personales, socioeconómicos y de contacto
 * de una matrícula existente para precargar el formulario de inscripción a
 * un nuevo curso, permitiendo al operador validar y actualizar la información
 * sin necesidad de re-ingresarla desde cero.
 *
 * Campos omitidos intencionalmente (corresponden al nuevo proceso):
 * curso_id, ciclo_id, fecha_matricula, fecha_inicio, monto, valor_cuota,
 * observaciones, matriculado_por_id, comercial_id y status.
 *
 * @property-read int         $id
 * @property-read string|null $tipo_identificacion
 * @property-read string|null $departamento_expedicion
 * @property-read string|null $ciudad_expedicion
 * @property-read string|null $fecha_nacimiento
 * @property-read string|null $genero
 * @property-read string|null $estado_civil
 * @property-read string|null $grupo_sanguineo
 * @property-read string|null $rh
 * @property-read string|null $direccion
 * @property-read string|null $celular
 * @property-read string|null $telefono
 * @property-read int|null    $lugar_origen_id
 * @property-read string|null $nivel_educacion
 * @property-read string|null $ocupacion
 * @property-read string|null $empresa
 * @property-read int|null    $estrato
 * @property-read string|null $regimen_salud
 * @property-read bool|null   $enfermedad_prioritaria
 * @property-read bool|null   $discapacidad
 * @property-read bool|null   $conocimiento_curso
 * @property-read string|null $como_entero_curso
 * @property-read string|null $talla_overol
 * @property-read string|null $talla_botas
 * @property-read string|null $nombre_contacto
 * @property-read string|null $telefono_contacto
 * @property-read string|null $correo_contacto
 * @property-read bool        $aprueba_uso_imagen
 * @property-read string|null $multiculturalidad
 * @property-read string|null $foto
 */
class MatriculaPrecargaResource extends JsonResource
{
    /**
     * Transforma el recurso en un array con solo los campos personales.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'matricula_referencia_id' => $this->id,

            // ── Identificación ───────────────────────────────────────────────
            'tipo_identificacion'       => $this->tipo_identificacion,
            'tipo_identificacion_texto' => $this->tipo_identificacion_texto,
            'departamento_expedicion'   => $this->departamento_expedicion,
            'ciudad_expedicion'         => $this->ciudad_expedicion,

            // ── Datos personales ─────────────────────────────────────────────
            'fecha_nacimiento'   => $this->fecha_nacimiento?->format('Y-m-d'),
            'genero'             => $this->genero,
            'genero_texto'       => $this->genero_texto,
            'estado_civil'       => $this->estado_civil,
            'estado_civil_texto' => $this->estado_civil_texto,
            'grupo_sanguineo'    => $this->grupo_sanguineo,
            'rh'                 => $this->rh,
            'rh_texto'           => $this->rh_texto,
            'direccion'          => $this->direccion,
            'celular'            => $this->celular,
            'telefono'           => $this->telefono,
            'lugar_origen_id'    => $this->lugar_origen_id,
            'lugar_origen'       => $this->whenLoaded('lugarOrigen', fn () => $this->lugarOrigen ? [
                'id'       => $this->lugarOrigen->id,
                'pais'     => $this->lugarOrigen->pais,
                'provincia' => $this->lugarOrigen->provincia,
                'nombre'   => $this->lugarOrigen->nombre,
            ] : null),

            // ── Datos socioeconómicos ────────────────────────────────────────
            'nivel_educacion'        => $this->nivel_educacion,
            'nivel_educacion_texto'  => $this->nivel_educacion_texto,
            'ocupacion'              => $this->ocupacion,
            'empresa'                => $this->empresa,
            'estrato'                => $this->estrato,
            'regimen_salud'          => $this->regimen_salud,
            'regimen_salud_texto'    => $this->regimen_salud_texto,
            'enfermedad_prioritaria' => $this->enfermedad_prioritaria,
            'discapacidad'           => $this->discapacidad,

            // ── Proceso de inscripción ───────────────────────────────────────
            'conocimiento_curso' => $this->conocimiento_curso,
            'como_entero_curso'  => $this->como_entero_curso,

            // ── Dotación ─────────────────────────────────────────────────────
            'talla_overol' => $this->talla_overol,
            'talla_botas'  => $this->talla_botas,

            // ── Contacto de emergencia ───────────────────────────────────────
            'nombre_contacto'   => $this->nombre_contacto,
            'telefono_contacto' => $this->telefono_contacto,
            'correo_contacto'   => $this->correo_contacto,

            // ── Consentimientos e identidad cultural ─────────────────────────
            'aprueba_uso_imagen' => $this->aprueba_uso_imagen,
            'multiculturalidad'  => $this->multiculturalidad,
            'foto'               => $this->foto,
            'foto_url'           => $this->foto && Storage::disk('public')->exists($this->foto)
                                        ? asset('storage/' . $this->foto)
                                        : null,
        ];
    }
}
