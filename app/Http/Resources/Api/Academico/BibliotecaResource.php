<?php

namespace App\Http\Resources\Api\Academico;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Academico\Biblioteca
 */

class BibliotecaResource extends JsonResource
{
    /**
     * Transforma el recurso en una matriz.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'nombre'              => $this->nombre,
            'fecha_carga'         => $this->fecha_carga?->toDateString(),
            'fecha_obsolescencia' => $this->fecha_obsolescencia?->toDateString(),
            'ruta'                => $this->ruta,
            'url'                 => Storage::disk('public')->exists($this->ruta)
                                        ? asset('storage/' . $this->ruta)
                                        : null,
            'tipo_archivo'        => $this->tipo_archivo,
            'tamanio'             => $this->tamanio,
            'tamanio_legible'     => $this->tamanioLegible(),
            'status'              => $this->status,
            'vigente'             => $this->esVigente(),

            /** @var array<array<string, mixed>> */
            'cursos' => $this->when($this->relationLoaded('cursos'), function () {
                return $this->cursos->map(fn ($curso) => [
                    'id'       => $curso->id,
                    'nombre'   => $curso->nombre,
                    'duracion' => $curso->duracion,
                    'status'   => $curso->status,
                ])->values()->toArray();
            }, []),

            'cursos_count' => $this->when(isset($this->cursos_count), $this->cursos_count),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }

    /**
     * Indica si el documento es vigente (sin obsolescencia o con fecha futura).
     */
    private function esVigente(): bool
    {
        if (is_null($this->fecha_obsolescencia)) {
            return true;
        }

        return $this->fecha_obsolescencia->greaterThanOrEqualTo(now()->startOfDay());
    }

    /**
     * Devuelve el tamaño del archivo en formato legible (KB, MB, etc.).
     */
    private function tamanioLegible(): ?string
    {
        if (is_null($this->tamanio)) {
            return null;
        }

        $bytes = $this->tamanio;

        if ($bytes < 1024) {
            return "{$bytes} B";
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}
