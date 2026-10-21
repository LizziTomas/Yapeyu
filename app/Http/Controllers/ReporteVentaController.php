<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        Gate::authorize('ver reportes');

        [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'consultaBase' => $consultaBase,
            'usuarios' => $usuarios,
        ] = $this->construirConsulta($request);

        // Ventas completadas en el rango: base de todos los totales del reporte
        $baseQuery = (clone $consultaBase)->where('estado', 'completada');

        // Ventas anuladas del período: se informan aparte y NO afectan los totales
        $cantidadAnuladas = (int) (clone $consultaBase)->where('estado', 'anulada')->count();

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

        // Serie temporal del período para el gráfico de evolución
        [$etiquetasSerie, $valoresSerie, $agrupacionSerie] = $this->construirSerie(
            (clone $baseQuery),
            $fechaDesde,
            $fechaHasta
        );

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
            'cantidadAnuladas',
            'totalEfectivo',
            'totalTransferencia',
            'totalTarjeta',
            'fechaDesde',
            'fechaHasta',
            'etiquetasSerie',
            'valoresSerie',
            'agrupacionSerie'
        ));
    }

    /**
     * Construye la serie de ventas completadas a lo largo del período.
     * Si el rango es corto se agrupa por día; si es extenso, por mes.
     *
     * @return array{0: array<int, string>, 1: array<int, float>, 2: string}
     */
    private function construirSerie($baseQuery, string $fechaDesde, string $fechaHasta): array
    {
        $inicio = Carbon::parse($fechaDesde, $this->zonaHoraria)->startOfDay();
        $fin = Carbon::parse($fechaHasta, $this->zonaHoraria)->startOfDay();

        $cantidadDias = $inicio->diffInDays($fin) + 1;

        // Se agrupa en PHP porque la base guarda UTC y el día del negocio es hora argentina
        $ventas = $baseQuery->get(['created_at', 'total']);

        if ($cantidadDias > 92) {
            // Períodos largos: se agrupa por mes para que el gráfico siga siendo legible
            $totales = $ventas->groupBy(fn (Venta $venta) => $venta->created_at->setTimezone($this->zonaHoraria)->format('Y-m'))
                ->map(fn ($grupo) => round((float) $grupo->sum('total'), 2));

            $etiquetas = [];
            $valores = [];
            $cursor = $inicio->copy()->startOfMonth();

            while ($cursor->lte($fin)) {
                $clave = $cursor->format('Y-m');
                $etiquetas[] = $cursor->format('m/Y');
                $valores[] = $totales->get($clave, 0.0);
                $cursor->addMonth();
            }

            return [$etiquetas, $valores, 'mensual'];
        }

        $totales = $ventas->groupBy(fn (Venta $venta) => $venta->created_at->setTimezone($this->zonaHoraria)->toDateString())
            ->map(fn ($grupo) => round((float) $grupo->sum('total'), 2));

        $etiquetas = [];
        $valores = [];
        $cursor = $inicio->copy();

        while ($cursor->lte($fin)) {
            $clave = $cursor->toDateString();
            $etiquetas[] = $cursor->format('d/m');
            $valores[] = $totales->get($clave, 0.0);
            $cursor->addDay();
        }

        return [$etiquetas, $valores, 'diaria'];
    }

    /**
     * Exporta el detalle de ventas del período (mismos filtros que el reporte) en formato CSV.
     * Se prepara para Excel en español: separador ";", decimales con coma y BOM UTF-8.
     */
    public function exportar(Request $request): StreamedResponse
    {
        Gate::authorize('ver reportes');

        [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'consultaBase' => $consultaBase,
        ] = $this->construirConsulta($request);

        // Se exporta exactamente el mismo conjunto que muestra la tabla del reporte
        $ventas = (clone $consultaBase)
            ->where('estado', 'completada')
            ->with(['user'])
            ->orderBy('created_at')
            ->get();

        $nombreArchivo = "reporte_ventas_{$fechaDesde}_a_{$fechaHasta}.csv";

        return response()->streamDownload(function () use ($ventas, $fechaDesde, $fechaHasta) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel reconozca correctamente los acentos
            fwrite($salida, "\xEF\xBB\xBF");

            $tituloPeriodo = Carbon::parse($fechaDesde)->format('d/m/Y')
                .' al '.Carbon::parse($fechaHasta)->format('d/m/Y');

            fputcsv($salida, ['Reporte de Ventas', configuracion()->nombre_local], ';');
            fputcsv($salida, ['Período', $tituloPeriodo], ';');
            fputcsv($salida, ['Generado', Carbon::now($this->zonaHoraria)->format('d/m/Y H:i')], ';');
            fputcsv($salida, [], ';');

            fputcsv($salida, ['N° Venta', 'Fecha', 'Hora', 'Vendedor', 'Caja', 'Medio de pago', 'Estado', 'Total'], ';');

            $totalGeneral = 0.0;

            foreach ($ventas as $venta) {
                $fecha = $venta->created_at->setTimezone($this->zonaHoraria);
                $totalGeneral += (float) $venta->total;

                fputcsv($salida, [
                    $venta->id,
                    $fecha->format('d/m/Y'),
                    $fecha->format('H:i'),
                    $venta->user->name ?? 'Usuario Eliminado',
                    'Caja #'.$venta->caja_id,
                    ucfirst($venta->medio_pago),
                    $venta->estado,
                    monto($venta->total),
                ], ';');
            }

            fputcsv($salida, [], ';');
            fputcsv($salida, ['', '', '', '', '', '', 'TOTAL', monto($totalGeneral)], ';');
            fputcsv($salida, ['Cantidad de operaciones', count($ventas)], ';');

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Construye la consulta base del reporte aplicando el período y los filtros solicitados,
     * incluyendo el control de autorización (un vendedor solo ve sus propias ventas).
     *
     * @return array{fechaDesde: string, fechaHasta: string, consultaBase: \Illuminate\Database\Eloquent\Builder, usuarios: \Illuminate\Support\Collection}
     */
    private function construirConsulta(Request $request): array
    {
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

        // Consulta base del período: filtros comunes a completadas y anuladas
        $consultaBase = Venta::whereBetween('created_at', [$inicioUtc, $finUtc]);

        // Control de autorización estricto en backend:
        // Si no es admin, se restringe forzosamente al usuario autenticado
        if (! $user->isAdmin()) {
            $consultaBase->where('user_id', $user->id);
            $usuarios = collect();
        } else {
            if ($request->filled('user_id')) {
                $consultaBase->where('user_id', $request->input('user_id'));
            }
            $usuarios = User::orderBy('name')->get();
        }

        // Filtro opcional por medio de pago
        if ($request->filled('medio_pago')) {
            $consultaBase->where('medio_pago', $request->input('medio_pago'));
        }

        return [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'consultaBase' => $consultaBase,
            'usuarios' => $usuarios,
        ];
    }
}
