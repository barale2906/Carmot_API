<?php

namespace App\Models\Academico;

use App\Traits\HasActiveStatus;
use App\Traits\HasFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Biblioteca extends Model
{
    use HasFactory, SoftDeletes, HasFilterScopes, HasSortingScopes, HasRelationScopes, HasActiveStatus;

    protected $table = 'biblioteca';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['deleted_at', 'fecha_carga', 'fecha_obsolescencia'];

    protected $casts = [
        'fecha_carga'          => 'date',
        'fecha_obsolescencia'  => 'date',
        'tamanio'              => 'integer',
        'status'               => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    /**
     * Cursos a los que pertenece este documento de biblioteca.
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'biblioteca_curso')
                    ->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // Scopes (sobrescriben los del trait cuando la lógica lo requiere)
    // -------------------------------------------------------------------------

    /**
     * Busca por nombre del documento.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('nombre', 'like', '%' . $search . '%');
    }

    /**
     * Filtra documentos vigentes (sin fecha de obsolescencia o con fecha futura).
     */
    public function scopeVigentes($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fecha_obsolescencia')
              ->orWhere('fecha_obsolescencia', '>=', now()->toDateString());
        });
    }

    /**
     * Filtra documentos obsoletos (fecha de obsolescencia pasada).
     */
    public function scopeObsoletos($query)
    {
        return $query->whereNotNull('fecha_obsolescencia')
                     ->where('fecha_obsolescencia', '<', now()->toDateString());
    }

    /**
     * Filtra por tipo de archivo.
     */
    public function scopeByTipoArchivo($query, string $tipo)
    {
        return $query->where('tipo_archivo', $tipo);
    }

    /**
     * Filtra documentos asociados a un curso específico.
     */
    public function scopeByCurso($query, int $cursoId)
    {
        return $query->whereHas('cursos', fn ($q) => $q->where('cursos.id', $cursoId));
    }

    /**
     * Aplica múltiples filtros de manera dinámica.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['search']) && $filters['search'],
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== null,
                fn ($q) => $q->byStatus($filters['status'])
            )
            ->when(
                isset($filters['tipo_archivo']) && $filters['tipo_archivo'],
                fn ($q) => $q->byTipoArchivo($filters['tipo_archivo'])
            )
            ->when(
                isset($filters['curso_id']) && $filters['curso_id'],
                fn ($q) => $q->byCurso($filters['curso_id'])
            )
            ->when(
                isset($filters['vigentes']) && $filters['vigentes'],
                fn ($q) => $q->vigentes()
            )
            ->when(
                isset($filters['obsoletos']) && $filters['obsoletos'],
                fn ($q) => $q->obsoletos()
            )
            ->when(
                isset($filters['fecha_carga_desde']) && $filters['fecha_carga_desde'],
                fn ($q) => $q->where('fecha_carga', '>=', $filters['fecha_carga_desde'])
            )
            ->when(
                isset($filters['fecha_carga_hasta']) && $filters['fecha_carga_hasta'],
                fn ($q) => $q->where('fecha_carga', '<=', $filters['fecha_carga_hasta'])
            )
            ->when(
                isset($filters['include_trashed']) && $filters['include_trashed'],
                fn ($q) => $q->withTrashed()
            )
            ->when(
                isset($filters['only_trashed']) && $filters['only_trashed'],
                fn ($q) => $q->onlyTrashed()
            );
    }

    // -------------------------------------------------------------------------
    // Métodos de soporte para HasRelationScopes / HasSortingScopes
    // -------------------------------------------------------------------------

    protected function getAllowedRelations(): array
    {
        return ['cursos'];
    }

    protected function getDefaultRelations(): array
    {
        return ['cursos'];
    }

    protected function getCountableRelations(): array
    {
        return ['cursos'];
    }

    protected function getAllowedSortFields(): array
    {
        return [
            'nombre',
            'fecha_carga',
            'fecha_obsolescencia',
            'tipo_archivo',
            'tamanio',
            'status',
            'created_at',
            'updated_at',
        ];
    }
}
