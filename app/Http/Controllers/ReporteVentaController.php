<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReporteVentaController extends Controller
{
    /**
     * Zona horaria oficial del negocio.
     */
    protected string $zonaHoraria = 'America/Argentina/Buenos_Aires';

    /**
     * Muestra el reporte consolidado y detallado de ventas por período.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Venta::class);

        /** @var User $user */
        $user = Auth::user();

        // Valores por defecto si no se especifican fechas
        $hoyArgentina = Carbon::now($this->zonaHoraria)->toDateString();
        $primerDiaMes = Carbon::now($this->zonaHoraria)->startOfMonth()->toDateString();

        $fechaDesde = $request->filled('fecha_desde') ? $request->input('fecha_desde') : $primerDiaMes;
        $fechaHasta = $request->filled('fecha_hasta') ? $request->input('fecha_hasta') : $hoyArgentina;

        // Validación de entradas
        $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'medio_pago' => ['nullable', 'string', 'in:efectivo,transferencia,tarjeta'],
        ], [
            'fecha_desde.date' => 'La fecha desde debe ser una fecha válida.',
            'fecha_hasta.date' => 'La fecha hasta debe ser una fecha válida.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'user_id.exists' => 'El vendedor seleccionado no existe.',
            'medio_pago.in' => 'El medio de pago seleccionado es inválido.',
        ]);

        // Conversión precisa de los límites del día en Argentina a UTC para la base de datos
        $inicioUtc = Carbon::createFromFormat('Y-m-d', $fechaDesde, $this->zonaHoraria)
            ->startOfDay()
            ->setTimezone('UTC');

        $finUtc = Carbon::createFromFormat('Y-m-d', $fechaHasta, $this->zonaHoraria)
            ->endOfDay()
            ->setTimezone('UTC');

        // Construcción de la consulta base: SOLO ventas completadas en el rango
        $baseQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [$inicioUtc, $finUtc]);

        // Control de autorización estricto en backend:
        // Si no es admin, se restringe forzosamente al usuario autenticado
        if (! $user->isAdmin()) {
            $baseQuery->where('user_id', $user->id);
            $usuarios = collect();
        } else {
            if ($request->filled('user_id')) {
                $baseQuery->where('user_id', $request->input('user_id'));
            }
            $usuarios = User::orderBy('name')->get();
        }

        // Filtro opcional por medio de pago
        if ($request->filled('medio_pago')) {
            $baseQuery->where('medio_pago', $request->input('medio_pago'));
        }

        // 1. Resumen consolidado del período
        $cantidadVentas = (int) (clone $baseQuery)->count();
        $totalVendido = (float) (clone $baseQuery)->sum('total');

        // Desglose por medios de pago
        $mediosPagoTotales = (clone $baseQuery)
            ->selectRaw('medio_pago, SUM(total) as total')
            ->groupBy('medio_pago')
            ->pluck('total', 'medio_pago');

        $totalEfectivo = (float) ($mediosPagoTotales['efectivo'] ?? 0);
        $totalTransferencia = (float) ($mediosPagoTotales['transferencia'] ?? 0);
        $totalTarjeta = (float) ($mediosPagoTotales['tarjeta'] ?? 0);

        // 2. Tabla detallada de operaciones paginadas
        $ventas = (clone $baseQuery)
            ->with(['user', 'caja'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('reportes.ventas', compact(
            'ventas',
            'usuarios',
            'cantidadVentas',
            'totalVendido',
            'totalEfectivo',
            'totalTransferencia',
            'totalTarjeta',
            'fechaDesde',
            'fechaHasta'
        ));
    }
}
