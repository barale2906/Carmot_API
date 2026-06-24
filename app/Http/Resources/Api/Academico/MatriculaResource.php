<?php

namespace App\Http\Resources\Api\Academico;

use App\Models\Academico\Matricula;
use App\Traits\HasActiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MatriculaResource extends JsonResource
{
    use HasActiveStatus;

    /**
     * Opciones de estado: extiende el trait para incluir "Anulado".
     */
    public static function getActiveStatusOptions(): array
    {
        return [
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Anulado',
        ];
    }

    /**
     * Transforma el recurso en un array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // ----------------------------------------------------------------
            // Datos académicos / administrativos
            // ----------------------------------------------------------------
            'fecha_matricula'  => $this->fecha_matricula?->format('Y-m-d'),
            'fecha_inicio'     => $this->fecha_inicio?->format('Y-m-d'),
            'monto'            => (float) $this->monto,
            'valor_cuota'      => $this->valor_cuota !== null ? (float) $this->valor_cuota : null,
            'observaciones'    => $this->observaciones,
            'status'           => $this->status,
            'status_text'      => self::getActiveStatusText($this->status),
            'anulada'          => $this->anulada,
            'activa'           => $this->activa,

            // ----------------------------------------------------------------
            // Datos de identificación
            // ----------------------------------------------------------------
            'tipo_identificacion'          => $this->tipo_identificacion,
            'tipo_identificacion_texto'    => $this->tipo_identificacion_texto,
            'departamento_expedicion'      => $this->departamento_expedicion,
            'ciudad_expedicion'            => $this->ciudad_expedicion,

            // ----------------------------------------------------------------
            // Datos personales
            // ----------------------------------------------------------------
            'fecha_nacimiento'    => $this->fecha_nacimiento?->format('Y-m-d'),
            'genero'              => $this->genero,
            'genero_texto'        => $this->genero_texto,
            'estado_civil'        => $this->estado_civil,
            'estado_civil_texto'  => $this->estado_civil_texto,
            'grupo_sanguineo'     => $this->grupo_sanguineo,
            'rh'                  => $this->rh,
            'rh_texto'            => $this->rh_texto,
            'direccion'           => $this->direccion,
            'celular'             => $this->celular,
            'telefono'            => $this->telefono,

            // ----------------------------------------------------------------
            // Datos socioeconómicos
            // ----------------------------------------------------------------
            'nivel_educacion'        => $this->nivel_educacion,
            'nivel_educacion_texto'  => $this->nivel_educacion_texto,
            'ocupacion'              => $this->ocupacion,
            'empresa'                => $this->empresa,
            'estrato'                => $this->estrato,
            'regimen_salud'          => $this->regimen_salud,
            'regimen_salud_texto'    => $this->regimen_salud_texto,

            // ----------------------------------------------------------------
            // Datos de salud y condición
            // ----------------------------------------------------------------
            'enfermedad_prioritaria' => $this->enfermedad_prioritaria,
            'discapacidad'           => $this->discapacidad,

            // ----------------------------------------------------------------
            // Proceso de venta / inscripción
            // ----------------------------------------------------------------
            'conocimiento_curso' => $this->conocimiento_curso,
            'como_entero_curso'  => $this->como_entero_curso,

            // ----------------------------------------------------------------
            // Dotación
            // ----------------------------------------------------------------
            'talla_overol' => $this->talla_overol,
            'talla_botas'  => $this->talla_botas,

            // ----------------------------------------------------------------
            // Contacto de emergencia
            // ----------------------------------------------------------------
            'nombre_contacto'   => $this->nombre_contacto,
            'telefono_contacto' => $this->telefono_contacto,
            'correo_contacto'   => $this->correo_contacto,

            // ----------------------------------------------------------------
            // Consentimientos e identidad cultural
            // ----------------------------------------------------------------
            'aprueba_uso_imagen' => $this->aprueba_uso_imagen,
            'multiculturalidad'  => $this->multiculturalidad,
            'foto'               => $this->foto,
            'foto_url'           => $this->foto && Storage::disk('public')->exists($this->foto)
                                        ? asset('storage/' . $this->foto)
                                        : null,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),

            // ----------------------------------------------------------------
            // Relaciones cargadas condicionalmente
            // ----------------------------------------------------------------
            'curso' => $this->whenLoaded('curso', [
                'id'          => $this->curso->id,
                'nombre'      => $this->curso->nombre,
                'duracion'    => $this->curso->duracion,
                'status'      => $this->curso->status,
                'status_text' => self::getActiveStatusText($this->curso->status),
            ]),

            'ciclo' => $this->whenLoaded('ciclo', [
                'id'          => $this->ciclo->id,
                'nombre'      => $this->ciclo->nombre,
                'descripcion' => $this->ciclo->descripcion,
                'fecha_inicio' => $this->ciclo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin'   => $this->ciclo->fecha_fin?->format('Y-m-d'),
                'status'      => $this->ciclo->status,
                'status_text' => self::getActiveStatusText($this->ciclo->status),
            ]),

            'estudiante' => $this->whenLoaded('estudiante', [
                'id'        => $this->estudiante->id,
                'name'      => $this->estudiante->name,
                'email'     => $this->estudiante->email,
                'documento' => $this->estudiante->documento,
            ]),

            'matriculado_por' => $this->whenLoaded('matriculadoPor', [
                'id'    => $this->matriculadoPor->id,
                'name'  => $this->matriculadoPor->name,
                'email' => $this->matriculadoPor->email,
            ]),

            'comercial' => $this->whenLoaded('comercial', [
                'id'    => $this->comercial->id,
                'name'  => $this->comercial->name,
                'email' => $this->comercial->email,
            ]),

            'lugar_origen' => $this->whenLoaded('lugarOrigen', fn () => $this->lugarOrigen ? [
                'id'       => $this->lugarOrigen->id,
                'pais'     => $this->lugarOrigen->pais,
                'provincia' => $this->lugarOrigen->provincia,
                'nombre'   => $this->lugarOrigen->nombre,
            ] : null),
        ];
    }
}
