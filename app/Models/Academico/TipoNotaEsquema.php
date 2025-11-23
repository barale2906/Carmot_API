<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo TipoNotaEsquema
 *
 * Representa un tipo de nota dentro de un esquema de calificación.
 * Define el nombre, peso y rango de valores permitidos para cada tipo de evaluación.
 *
 * @property int $id Identificador único del tipo de nota
 * @property int $esquema_calificacion_id ID del esquema al que pertenece
 * @property string $nombre_tipo Nombre del tipo de nota
 * @property float $peso Peso en porcentaje (0-100)
 * @property int $orden Orden de visualización
 * @property float $nota_minima Nota mínima permitida
 * @property float $nota_maxima Nota máxima permitida
 * @property string|null $descripcion Descripción del tipo de nota
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 *
 * @property-read \App\Models\Academico\EsquemaCalificacion $esquemaCalificacion Esquema al que pertenece
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Academico\NotaEstudiante[] $notasEstudiantes Notas registradas de este tipo
 */
class TipoNotaEsquema extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'peso' => 'decimal:2',
        'orden' => 'integer',
        'nota_minima' => 'decimal:2',
        'nota_maxima' => 'decimal:2',
    ];

    /**
     * Relación con EsquemaCalificacion (muchos a uno).
     * Un tipo de nota pertenece a un esquema.
     *
     * @return BelongsTo
     */
    public function esquemaCalificacion(): BelongsTo
    {
        return $this->belongsTo(EsquemaCalificacion::class);
    }

    /**
     * Relación con NotaEstudiante (uno a muchos).
     * Un tipo de nota tiene múltiples notas de estudiantes.
     *
     * @return HasMany
     */
    public function notasEstudiantes(): HasMany
    {
        return $this->hasMany(NotaEstudiante::class);
    }

    /**
     * Scope para filtrar por esquema.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $esquemaId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEsquema($query, $esquemaId)
    {
        return $query->where('esquema_calificacion_id', $esquemaId);
    }

    /**
     * Scope para ordenar por orden.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }

    /**
     * Verifica si una nota está dentro del rango permitido.
     *
     * @param float $nota
     * @return bool
     */
    public function notaValida(float $nota): bool
    {
        return $nota >= $this->nota_minima && $nota <= $this->nota_maxima;
    }
}
