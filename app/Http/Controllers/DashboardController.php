<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Muestra el panel de control principal con el resumen del negocio.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        // Rango de fechas ajustado a la zona horaria de Argentina
        $zonaHoraria = 'America/Argentina/Buenos_Aires';
        $inicioDia = Carbon::now($zonaHoraria)->startOfDay()->setTimezone('UTC');
        $finDia = Carbon::now($zonaHoraria)->endOfDay()->setTimezone('UTC');
        $inicioMes = Carbon::now($zonaHoraria)->startOfMonth()->setTimezone('UTC');
        $finMes = Carbon::now($zonaHoraria)->endOfMonth()->setTimezone('UTC');

        // 1. Consulta de Ventas del Día
        $ventasHoyQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [$inicioDia, $finDia]);

        if (! $user->isAdmin()) {
            $ventasHoyQuery->where('user_id', $user->id);
        }

        $totalVendidoHoy = (float) (clone $ventasHoyQuery)->sum('total');
        $cantidadVentasHoy = (int) (clone $ventasHoyQuery)->count();

        // 1b. Comparación contra el día anterior (tendencia del KPI principal)
        $inicioAyer = Carbon::now($zonaHoraria)->subDay()->startOfDay()->setTimezone('UTC');
        $finAyer = Carbon::now($zonaHoraria)->subDay()->endOfDay()->setTimezone('UTC');

        $ventasAyerQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [$inicioAyer, $finAyer]);

        if (! $user->isAdmin()) {
            $ventasAyerQuery->where('user_id', $user->id);
        }

        $totalVendidoAyer = (float) $ventasAyerQuery->sum('total');

        // Variación porcentual respecto de ayer (null cuando no hay base de comparación)
        $variacionAyer = null;
        if ($totalVendidoAyer > 0) {
            $variacionAyer = round((($totalVendidoHoy - $totalVendidoAyer) / $totalVendidoAyer) * 100, 1);
        }

        // 2. Desglose de Medios de Pago de Hoy
        $mediosPagoHoy = (clone $ventasHoyQuery)
            ->selectRaw('medio_pago, SUM(total) as total')
            ->groupBy('medio_pago')
            ->pluck('total', 'medio_pago');

        $efectivoHoy = (float) ($mediosPagoHoy['efectivo'] ?? 0);
        $transferenciaHoy = (float) ($mediosPagoHoy['transferencia'] ?? 0);
        $tarjetaHoy = (float) ($mediosPagoHoy['tarjeta'] ?? 0);

        // 2b. Serie de los últimos 7 días para el gráfico de evolución
        $inicioSemana = Carbon::now($zonaHoraria)->subDays(6)->startOfDay();
        $finSemana = Carbon::now($zonaHoraria)->endOfDay();

        $ventasSemanaQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [
                $inicioSemana->copy()->setTimezone('UTC'),
                $finSemana->copy()->setTimezone('UTC'),
            ]);

        if (! $user->isAdmin()) {
            $ventasSemanaQuery->where('user_id', $user->id);
        }

        // Se agrupa en PHP por día del negocio (la base guarda UTC, el negocio opera en hora argentina)
        $totalesPorDia = $ventasSemanaQuery->get(['created_at', 'total'])
            ->groupBy(fn (Venta $venta) => $venta->created_at->setTimezone($zonaHoraria)->toDateString())
            ->map(fn ($grupo) => round((float) $grupo->sum('total'), 2));

        $diasSemana = [];
        $etiquetasSemana = [];
        $totalesSemana = [];

        for ($i = 6; $i >= 0; $i--) {
            $dia = Carbon::now($zonaHoraria)->subDays($i);
            $clave = $dia->toDateString();

            $diasSemana[] = [
                'etiqueta' => $dia->translatedFormat('D').' '.$dia->format('d/m'),
                'iso' => $clave,
                'total' => $totalesPorDia->get($clave, 0.0),
            ];

            $etiquetasSemana[] = $dia->format('d/m');
            $totalesSemana[] = $totalesPorDia->get($clave, 0.0);
        }

        // Total vendido en los últimos 7 días (dato de apoyo del gráfico)
        $totalSemana = round((float) array_sum($totalesSemana), 2);

        // 3. Consulta de Ventas del Mes
        $ventasMesQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [$inicioMes, $finMes]);

        if (! $user->isAdmin()) {
            $ventasMesQuery->where('user_id', $user->id);
        }

        $totalVendidoMes = (float) (clone $ventasMesQuery)->sum('total');
        $cantidadVentasMes = (int) (clone $ventasMesQuery)->count();

        // 4. Métricas de Stock e Inventario
        $totalProductosActivos = Producto::activos()->count();
        $totalUnidadesStock = (int) Producto::activos()->sum('stock');
        $totalValorCosto = (float) (Producto::activos()->selectRaw('SUM(stock * precio_costo) as total')->value('total') ?? 0);

        // 5. Alertas de Stock Bajo
        $productosStockBajoQuery = Producto::activos()->whereColumn('stock', '<=', 'stock_minimo');
        $cantidadStockBajo = $productosStockBajoQuery->count();
        $productosStockBajoLista = $productosStockBajoQuery->orderBy('stock', 'asc')->take(5)->get();

        // 6. Estado de Caja del Usuario
        $cajaAbierta = $user->cajaAbierta();
        $cajasAbiertasNegocio = $user->isAdmin() ? Caja::abiertas()->with('user')->get() : collect();

        return view('dashboard', compact(
            'totalVendidoHoy',
            'cantidadVentasHoy',
            'totalVendidoAyer',
            'variacionAyer',
            'efectivoHoy',
            'transferenciaHoy',
            'tarjetaHoy',
            'totalVendidoMes',
            'cantidadVentasMes',
            'totalProductosActivos',
            'totalUnidadesStock',
            'totalValorCosto',
            'cantidadStockBajo',
            'productosStockBajoLista',
            'cajaAbierta',
            'cajasAbiertasNegocio',
            'etiquetasSemana',
            'totalesSemana',
            'totalSemana',
            'diasSemana'
        ));
    }
}
