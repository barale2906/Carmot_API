<?php

namespace App\Models\Financiero\LibroDiario;

use App\Traits\HasActiveStatus;
use App\Traits\HasSortingScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo LdTercero
 *
 * Persona o empresa a quien se paga un egreso o de quien se recibe otro ingreso en
 * el libro diario. Se identifica por tipo + número de identificación (único) y se
 * crea desde el mismo registro del movimiento cuando aún no existe. No se elimina
 * (queda referenciado en los movimientos): se inactiva.
 *
 * @property int $id
 * @property string $tipo_identificacion
 * @property string $identificacion
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property int $status 0=Inactivo, 1=Activo
 * @property-read \Illuminate\Database\Eloquent\Collection|LdMovimiento[] $movimientos
 */
class LdTercero extends Model
{
    use HasActiveStatus, HasFactory, HasSortingScopes;

    /** Tipos de identificación admitidos. */
    public const TIPOS_IDENTIFICACION = [
        'CC' => 'Cédula de ciudadanía',
        'NIT' => 'NIT',
        'CE' => 'Cédula de extranjería',
        'PA' => 'Pasaporte',
        'TI' => 'Tarjeta de identidad',
    ];

    protected $table = 'ld_terceros';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Movimientos registrados a nombre de este tercero.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(LdMovimiento::class, 'tercero_id');
    }

    /**
     * Scope de búsqueda por número de identificación o nombre.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(fn ($q) => $q
            ->where('identificacion', 'like', $search.'%')
            ->orWhere('nombre', 'like', '%'.$search.'%'));
    }

    /**
     * Scope compuesto con los filtros disponibles: search, status y tipo_identificacion.
     */
    public function scopeWithFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['tipo_identificacion']),
                fn ($q) => $q->where('tipo_identificacion', $filters['tipo_identificacion']));
    }

    /**
     * Tercero con ese tipo y número de identificación, si existe.
     */
    public static function porIdentificacion(string $tipo, string $identificacion): ?self
    {
        return self::where('tipo_identificacion', $tipo)->where('identificacion', $identificacion)->first();
    }

    /**
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['nombre', 'identificacion', 'status', 'created_at', 'updated_at'];
    }
}
