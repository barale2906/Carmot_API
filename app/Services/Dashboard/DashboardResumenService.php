<?php

namespace App\Services\Dashboard;

use App\Models\Academico\Ciclo;
use App\Models\Academico\Matricula;
use App\Models\Configuracion\DiaNoLaborable;
use App\Models\Crm\Agenda;
use App\Models\Crm\Referido;
use App\Models\Financiero\Cartera\Cartera;
use App\Models\Financiero\LibroDiario\LdTurnoCaja;
use App\Models\Financiero\ReciboPago\ReciboPago;
use App\Models\Inventarios\InvStock;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Arma el resumen del Dashboard con datos reales.
 *
 * Cada sección se incluye solo si el usuario tiene el permiso del módulo de donde sale
 * la cifra, y todo lo que pertenece a una sede se limita a las sedes que el usuario
 * puede ver (User::idsSedesAccesibles). Los leads y las citas del CRM no tienen sede.
 */
class DashboardResumenService
{
    /** Días sin gestión a partir de los cuales un lead "Creado" se considera desatendido. */
    private const DIAS_LEAD_SIN_GESTION = 3;

    /** Ventana de los próximos eventos. */
    private const DIAS_PROXIMOS_EVENTOS = 30;

    private User $usuario;

    /** @var array<int> */
    private array $sedes;

    /**
     * @param User $usuario
     * @return array{tarjetas: array, alertas: array, actividad: array, eventos: array, retencion: array|null}
     */
    public function para(User $usuario): array
    {
        $this->usuario = $usuario;
        $this->sedes   = $usuario->idsSedesAccesibles();

        return [
            'tarjetas'  => $this->tarjetas(),
            'alertas'   => $this->alertas(),
            'actividad' => $this->actividad(),
            'eventos'   => $this->eventos(),
            'retencion' => $this->usuario->can('aca_matriculas') ? $this->retencion() : null,
        ];
    }

    /**
     * Cifras principales (fila de tarjetas).
     */
    private function tarjetas(): array
    {
        $tarjetas = [];

        if ($this->usuario->can('aca_matriculas')) {
            $nuevasMes = Matricula::whereIn('sede_id', $this->sedes)
                ->where('status', '!=', 2)
                ->whereBetween('fecha_matricula', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();

            $tarjetas[] = [
                'clave'       => 'estudiantes_activos',
                'titulo'      => 'Estudiantes activos',
                'valor'       => Matricula::whereIn('sede_id', $this->sedes)
                    ->vigentes()
                    ->where('estado_academico', Matricula::ESTADO_ACTIVO)
                    ->distinct('estudiante_id')
                    ->count('estudiante_id'),
                'descripcion' => "{$nuevasMes} matrículas nuevas este mes",
                'ruta'        => '/academico/matriculas',
            ];
        }

        if ($this->usuario->can('crm_agendas')) {
            $tarjetas[] = [
                'clave'       => 'citas_hoy',
                'titulo'      => 'Citas pendientes hoy',
                'valor'       => Agenda::hoy()->pendientes()->count(),
                'descripcion' => 'Citas del CRM por atender hoy',
                'ruta'        => '/crm/agendas',
            ];
        }

        if ($this->usuario->can('fin_carteras')) {
            $vencida = $this->carteraVencida();
            $tarjetas[] = [
                'clave'       => 'cartera_vencida',
                'titulo'      => 'Estudiantes en mora',
                'valor'       => (clone $vencida)->distinct('estudiante_id')->count('estudiante_id'),
                'descripcion' => 'Saldo vencido: $' . number_format((float) (clone $vencida)->sum('saldo'), 0, ',', '.'),
                'ruta'        => '/financiero/cartera',
            ];
        }

        if ($this->usuario->can('inv_stock')) {
            $tarjetas[] = [
                'clave'       => 'bajo_stock',
                'titulo'      => 'Inventario bajo stock',
                'valor'       => InvStock::bajoStock()->count(),
                'descripcion' => 'Productos en o bajo su punto de reorden',
                'ruta'        => '/inventario/stock',
            ];
        }

        return $tarjetas;
    }

    /**
     * Pendientes que requieren acción de alguien con el permiso respectivo.
     * Solo se incluyen las alertas con al menos un caso.
     */
    private function alertas(): array
    {
        $alertas = [];

        if ($this->usuario->can('fin_reciboPagoAprobar')) {
            $alertas[] = [
                'clave'       => 'transferencias_por_aprobar',
                'titulo'      => 'Transferencias por aprobar',
                'descripcion' => 'Pagos por transferencia esperando validación',
                'cantidad'    => ReciboPago::whereIn('sede_id', $this->sedes)
                    ->where('status', ReciboPago::STATUS_PENDIENTE_APROBACION)
                    ->count(),
                'variante'    => 'warning',
                'ruta'        => '/financiero/transferencias-pendientes',
            ];
        }

        if ($this->usuario->can('fin_ldCierreAprobar')) {
            $alertas[] = [
                'clave'       => 'cierres_por_aprobar',
                'titulo'      => 'Cierres de caja por aprobar',
                'descripcion' => 'Turnos cerrados pendientes de revisión',
                'cantidad'    => LdTurnoCaja::whereIn('sede_id', $this->sedes)
                    ->where('status', LdTurnoCaja::STATUS_POR_APROBAR)
                    ->count(),
                'variante'    => 'warning',
                'ruta'        => '/financiero/turnos-caja',
            ];
        }

        if ($this->usuario->can('fin_carteras')) {
            $alertas[] = [
                'clave'       => 'cartera_vencida_30',
                'titulo'      => 'Cartera vencida hace más de 30 días',
                'descripcion' => 'Estudiantes con cuotas vencidas hace más de un mes',
                'cantidad'    => $this->carteraVencida()
                    ->where('fecha_vencimiento', '<', now()->subDays(30)->toDateString())
                    ->distinct('estudiante_id')
                    ->count('estudiante_id'),
                'variante'    => 'danger',
                'ruta'        => '/financiero/cartera',
            ];

            $alertas[] = [
                'clave'       => 'acuerdos_pago',
                'titulo'      => 'Acuerdos de pago vigentes',
                'descripcion' => 'Cuotas con acuerdo de pago por cumplir',
                'cantidad'    => Cartera::whereIn('sede_id', $this->sedes)
                    ->where('status', Cartera::getStatusKey('En Acuerdo'))
                    ->where('saldo', '>', 0)
                    ->count(),
                'variante'    => 'info',
                'ruta'        => '/financiero/cartera',
            ];
        }

        if ($this->usuario->can('crm_referidos')) {
            $alertas[] = [
                'clave'       => 'leads_sin_gestion',
                'titulo'      => 'Leads sin gestionar',
                'descripcion' => 'Leads en estado Creado hace más de ' . self::DIAS_LEAD_SIN_GESTION . ' días',
                'cantidad'    => Referido::where('status', 0)
                    ->where('created_at', '<', now()->subDays(self::DIAS_LEAD_SIN_GESTION))
                    ->count(),
                'variante'    => 'info',
                'ruta'        => '/crm/referidos',
            ];
        }

        return array_values(array_filter($alertas, fn (array $alerta) => $alerta['cantidad'] > 0));
    }

    /**
     * Últimas matrículas y pagos, mezclados del más reciente al más antiguo.
     */
    private function actividad(): array
    {
        $items = collect();

        if ($this->usuario->can('aca_matriculas')) {
            Matricula::whereIn('sede_id', $this->sedes)
                ->with(['estudiante', 'curso'])
                ->latest()
                ->limit(5)
                ->get()
                ->each(fn (Matricula $m) => $items->push([
                    'tipo'        => 'matricula',
                    'titulo'      => $m->status === 2 ? 'Matrícula anulada' : 'Nueva matrícula',
                    'descripcion' => trim(($m->estudiante?->name ?? 'Estudiante') . ' — ' . ($m->curso?->nombre ?? '')),
                    'fecha'       => $m->created_at,
                ]));
        }

        if ($this->usuario->can('fin_recibos_pago')) {
            ReciboPago::whereIn('sede_id', $this->sedes)
                ->whereIn('status', [ReciboPago::STATUS_CREADO, ReciboPago::STATUS_CERRADO])
                ->with('estudiante')
                ->latest()
                ->limit(5)
                ->get()
                ->each(fn (ReciboPago $r) => $items->push([
                    'tipo'        => 'pago',
                    'titulo'      => "Pago {$r->numero_recibo}",
                    'descripcion' => trim(($r->estudiante?->name ?? 'Venta de inventario') . ' — $' . number_format((float) $r->valor_total, 0, ',', '.')),
                    'fecha'       => $r->created_at,
                ]));
        }

        return $items->sortByDesc('fecha')
            ->take(8)
            ->map(fn (array $item) => [...$item, 'fecha' => $item['fecha']?->toIso8601String()])
            ->values()
            ->all();
    }

    /**
     * Inicios de ciclo y días no laborables de los próximos 30 días.
     */
    private function eventos(): array
    {
        $desde  = now()->toDateString();
        $hasta  = now()->addDays(self::DIAS_PROXIMOS_EVENTOS)->toDateString();
        $items  = collect();

        if ($this->usuario->can('aca_programaciones') || $this->usuario->can('aca_matriculas')) {
            Ciclo::whereIn('sede_id', $this->sedes)
                ->where('status', 1)
                ->whereBetween('fecha_inicio', [$desde, $hasta])
                ->with(['curso', 'sede'])
                ->get()
                ->each(fn (Ciclo $c) => $items->push([
                    'tipo'      => 'inicio_ciclo',
                    'fecha'     => $c->fecha_inicio,
                    'titulo'    => "Inicio de {$c->nombre}",
                    'subtitulo' => trim(($c->curso?->nombre ?? '') . ' — ' . ($c->sede?->nombre ?? '')),
                ]));
        }

        $noLaborables = DiaNoLaborable::where('status', 1)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('sede_id')->orWhereIn('sede_id', $this->sedes))
            ->with('sede')
            ->orderBy('fecha')
            ->get();

        foreach ($this->agruparDiasConsecutivos($noLaborables) as $tramo) {
            $items->push([
                'tipo'      => 'no_laborable',
                'fecha'     => $tramo['desde'],
                'fecha_fin' => $tramo['hasta']->equalTo($tramo['desde']) ? null : $tramo['hasta']->toDateString(),
                'titulo'    => $tramo['dia']->nombre,
                'subtitulo' => $tramo['dia']->sede?->nombre ?? 'Todas las sedes',
            ]);
        }

        return $items->sortBy(fn (array $item) => Carbon::parse($item['fecha'])->toDateString())
            ->take(6)
            ->map(fn (array $item) => ['fecha_fin' => null, ...$item, 'fecha' => Carbon::parse($item['fecha'])->toDateString()])
            ->values()
            ->all();
    }

    /**
     * Une en un solo tramo los días no laborables consecutivos con el mismo nombre y sede
     * (un cierre de una semana se registra día por día, pero es un único evento).
     *
     * @param \Illuminate\Support\Collection<int, DiaNoLaborable> $dias Ordenados por fecha
     * @return array<int, array{dia: DiaNoLaborable, desde: Carbon, hasta: Carbon}>
     */
    private function agruparDiasConsecutivos($dias): array
    {
        $tramos = [];

        foreach ($dias as $dia) {
            $clave  = $dia->nombre . '|' . $dia->sede_id;
            $ultimo = $tramos[$clave] ?? null;

            if ($ultimo && $ultimo['hasta']->copy()->addDay()->isSameDay($dia->fecha)) {
                $tramos[$clave]['hasta'] = $dia->fecha->copy();
                continue;
            }

            // Un tramo cerrado se conserva con una clave única antes de abrir el siguiente.
            if ($ultimo) {
                $tramos[$clave . '|' . $ultimo['desde']->toDateString()] = $ultimo;
            }
            $tramos[$clave] = ['dia' => $dia, 'desde' => $dia->fecha->copy(), 'hasta' => $dia->fecha->copy()];
        }

        return array_values($tramos);
    }

    /**
     * Tasa de retención de los últimos 12 meses: matrículas no anuladas que no terminaron
     * en deserción ni retiro, sobre el total de matrículas del periodo.
     *
     * @return array{tasa: float|null, matriculas: int, desertados: int, retirados: int}
     */
    private function retencion(): array
    {
        $base = Matricula::whereIn('sede_id', $this->sedes)
            ->where('status', '!=', 2)
            ->where('fecha_matricula', '>=', now()->subYear()->toDateString());

        $total      = (clone $base)->count();
        $desertados = (clone $base)->where('estado_academico', Matricula::ESTADO_DESERTADO)->count();
        $retirados  = (clone $base)->where('estado_academico', Matricula::ESTADO_RETIRADO)->count();

        return [
            'tasa'       => $total > 0 ? round(($total - $desertados - $retirados) / $total * 100, 1) : null,
            'matriculas' => $total,
            'desertados' => $desertados,
            'retirados'  => $retirados,
        ];
    }

    /**
     * Cuotas con saldo y fecha vencida en las sedes del usuario (sin anuladas, cerradas ni en acuerdo).
     */
    private function carteraVencida()
    {
        return Cartera::whereIn('sede_id', $this->sedes)
            ->whereIn('status', [Cartera::getStatusKey('Activa'), Cartera::getStatusKey('Abonada')])
            ->where('saldo', '>', 0)
            ->where('fecha_vencimiento', '<', now()->toDateString());
    }
}
