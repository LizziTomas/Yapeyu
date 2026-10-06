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

        // 2. Desglose de Medios de Pago de Hoy
        $mediosPagoHoy = (clone $ventasHoyQuery)
            ->selectRaw('medio_pago, SUM(total) as total')
            ->groupBy('medio_pago')
            ->pluck('total', 'medio_pago');

        $efectivoHoy = (float) ($mediosPagoHoy['efectivo'] ?? 0);
        $transferenciaHoy = (float) ($mediosPagoHoy['transferencia'] ?? 0);
        $tarjetaHoy = (float) ($mediosPagoHoy['tarjeta'] ?? 0);

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
            'cajasAbiertasNegocio'
        ));
    }
}
