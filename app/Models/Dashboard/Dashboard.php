<?php

namespace App\Models\Dashboard;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo Dashboard
 *
 * Representa un dashboard personalizable en el sistema.
 * Los dashboards contienen tarjetas (cards) que muestran KPIs específicos.
 *
 * @property int $id Identificador único del dashboard
 * @property int|null $tenant_id ID del tenant (opcional para sistemas multi-tenant)
 * @property int $user_id ID del usuario propietario del dashboard
 * @property string $name Nombre del dashboard (ej. "Dashboard de Ventas Q3")
 * @property bool $is_default Indica si es un dashboard general (true) o personal (false)
 * @property \Carbon\Carbon $created_at Fecha de creación
 * @property \Carbon\Carbon $updated_at Fecha de última actualización
 * @property \Carbon\Carbon|null $deleted_at Fecha de eliminación (soft delete)
 *
 * @property-read \App\Models\User $user Usuario propietario del dashboard
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Dashboard\DashboardCard[] $dashboardCards Tarjetas del dashboard
 */
class Dashboard extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que pueden ser asignados masivamente.
     *
     * @var array<string>
     */
    protected $fillable = ['tenant_id', 'user_id', 'name', 'is_default'];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Relación con User (muchos a uno).
     * Un dashboard pertenece a un usuario.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con DashboardCard (uno a muchos).
     * Un dashboard puede tener múltiples tarjetas.
     *
     * @return HasMany
     */
    public function dashboardCards(): HasMany
    {
        return $this->hasMany(DashboardCard::class);
    }

    /**
     * Indica si es un dashboard general (visible a todos los usuarios).
     */
    public function isGeneral(): bool
    {
        return $this->is_default === true;
    }

    /**
     * Indica si es un dashboard personal (del usuario propietario).
     */
    public function isPersonal(): bool
    {
        return $this->is_default === false;
    }

    /**
     * Relación con Tenant (muchos a uno).
     * Un dashboard puede pertenecer a un tenant (opcional).
     *
     * @return BelongsTo
     */
    /* public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    } */
}
