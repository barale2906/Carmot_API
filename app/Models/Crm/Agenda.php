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

    /** Tipos de acción que se programan con un lead. */
    public const TIPOS = [
        'llamada' => 'Llamada',
        'visita'  => 'Visita',
        'mensaje' => 'Mensaje / WhatsApp',
        'otro'    => 'Otro',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Texto de los resultados (1 y 2) según el tipo de acción: una visita se asiste, una
     * llamada se contesta, un mensaje se responde. Los demás estados son comunes a todos.
     */
    public const RESULTADOS_POR_TIPO = [
        'visita'  => [1 => 'Asistió', 2 => 'No asistió'],
        'llamada' => [1 => 'Contestó', 2 => 'No contestó'],
        'mensaje' => [1 => 'Respondió', 2 => 'Sin respuesta'],
        'otro'    => [1 => 'Realizada', 2 => 'No realizada'],
    ];

    /** Igual al default de la columna: las agendas sin tipo son citas presenciales. */
    protected $attributes = [
        'tipo' => 'visita',
    ];

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
     * Campos por los que se puede ordenar el listado.
     *
     * @return array
     */
    protected function getAllowedSortFields(): array
    {
        return ['fecha', 'hora', 'jornada', 'tipo', 'status', 'created_at', 'updated_at'];
    }

    /**
     * Relaciones que se pueden cargar vía `with`.
     *
     * @return array
     */
    protected function getAllowedRelations(): array
    {
        return ['referido', 'agendador'];
    }

    /**
     * Relaciones cargadas por defecto.
     *
     * @return array
     */
    protected function getDefaultRelations(): array
    {
        return ['referido', 'agendador'];
    }

    /**
     * La agenda no tiene relaciones contables.
     *
     * @return array
     */
    protected function getCountableRelations(): array
    {
        return [];
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
            'seguimiento' => "AGENDA: {$this->tipo_text} para el {$this->fecha->format('Y-m-d')} a las {$this->hora} ({$this->jornada})"
                . ($this->nota ? ". {$this->nota}" : ''),
        ]);
    }

    /**
     * Texto del tipo de acción programada.
     *
     * @return string
     */
    public function getTipoTextAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? 'Otro';
    }

    /**
     * Jornada ('am' | 'pm') que corresponde a una hora "HH:mm".
     *
     * @param string $hora
     * @return string
     */
    public static function jornadaDeHora(string $hora): string
    {
        return (int) substr($hora, 0, 2) >= 12 ? 'pm' : 'am';
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
            0 => 1, // Agendado -> Realizada
            1 => null, // Realizada -> No hay siguiente
            2 => 3, // No realizada -> Reprogramó
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
        return $nextStatus ? self::getStatusText($nextStatus, $this->tipo) : null;
    }

    /**
     * Opciones de estado de una agenda. Con tipo, los resultados (1 y 2) usan el texto de ese
     * tipo de acción; sin tipo, el texto genérico (Realizada / No realizada).
     *
     * @param string|null $tipo
     * @return array<int, string>
     */
    public static function getStatusOptions(?string $tipo = null): array
    {
        $resultados = self::RESULTADOS_POR_TIPO[$tipo] ?? self::RESULTADOS_POR_TIPO['otro'];

        return [
            0 => 'Agendado',
            1 => $resultados[1],
            2 => $resultados[2],
            3 => 'Reprogramó',
            4 => 'Canceló',
        ];
    }

    /**
     * Texto de un estado según el tipo de acción ({@see getStatusOptions}).
     *
     * @param int $status
     * @param string|null $tipo
     * @return string
     */
    public static function getStatusText(int $status, ?string $tipo = null): string
    {
        return self::getStatusOptions($tipo)[$status] ?? 'Desconocido';
    }

    /**
     * Texto del estado de esta agenda, según su tipo.
     *
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusText($this->status, $this->tipo);
    }

}
