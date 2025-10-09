<?php

namespace App\Models\Crm;

use App\Models\User;
use App\Models\Crm\Seguimiento;
use App\Traits\HasAgendaScopes;
use App\Traits\HasFilterScopes;
use App\Traits\HasSortingScopes;
use App\Traits\HasRelationScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agenda extends Model
{
    use HasFactory, SoftDeletes;
    use HasAgendaScopes, HasFilterScopes, HasSortingScopes, HasRelationScopes;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Obtiene los atributos que deben ser convertidos a fechas.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'fecha' => 'date',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot del modelo para registrar eventos.
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se ejecuta después de crear una agenda
        static::created(function ($agenda) {
            $agenda->crearSeguimientoAutomatico();
        });
    }

    /**
     * Relación con el referido al que pertenece este agendamiento.
     *
     * @return BelongsTo
     */
    public function referido() : BelongsTo
    {
        return $this->belongsTo(Referido::class);
    }

    /**
     * Relación con el usuario que genero el agendamiento.
     *
     * @return BelongsTo
     */
    public function agendador() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea automáticamente un seguimiento cuando se crea una agenda.
     *
     * @return void
     */
    public function crearSeguimientoAutomatico(): void
    {
        Seguimiento::create([
            'referido_id' => $this->referido_id,
            'seguidor_id' => $this->agendador_id,
            'fecha' => now(),
            'seguimiento' => "AGENDA: Cita agendada para el {$this->fecha} a las {$this->hora} ({$this->jornada})"
        ]);
    }


    /**
     * Determina si la agenda está agendada.
     *
     * @return bool
     */
    public function isAgendada(): bool
    {
        return $this->status === 0;
    }

    /**
     * Determina si la agenda fue completada (asistió).
     *
     * @return bool
     */
    public function isCompletada(): bool
    {
        return $this->status === 1;
    }

    /**
     * Determina si la agenda fue cancelada.
     *
     * @return bool
     */
    public function isCancelada(): bool
    {
        return $this->status === 4;
    }


    /**
     * Obtiene el siguiente estado sugerido basado en el estado actual.
     *
     * @return int|null
     */
    public function getNextSuggestedStatus(): ?int
    {
        return match ($this->status) {
            0 => 1, // Agendado -> Asistió
            1 => null, // Asistió -> No hay siguiente
            2 => 3, // No asistió -> Reprogramó
            3 => 0, // Reprogramó -> Agendado
            4 => null, // Canceló -> No hay siguiente
            default => null,
        };
    }

    /**
     * Obtiene el texto del siguiente estado sugerido.
     *
     * @return string|null
     */
    public function getNextSuggestedStatusText(): ?string
    {
        $nextStatus = $this->getNextSuggestedStatus();
        return $nextStatus ? self::getStatusText($nextStatus) : null;
    }

    /**
     * Obtiene el texto del status estático.
     *
     * @param int $status
     * @return string
     */
    public static function getStatusText(int $status): string
    {
        return match ($status) {
            0 => 'Agendado',
            1 => 'Asistió',
            2 => 'No asistió',
            3 => 'Reprogramó',
            4 => 'Canceló',
            default => 'Desconocido',
        };
    }
}
