<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConsultarReportesVentas implements Tool
{
    public function __construct(
        private readonly User $usuario
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta los reportes de ventas de Casa Yacobone. Devuelve cantidad de ventas completadas, total vendido, ventas anuladas, importes por medio de pago y evolucion temporal de las ventas. Permite filtrar por fechas, vendedor y medio de pago. Es una herramienta de solo lectura. Las fechas deben expresarse como YYYY-MM-DD.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fecha_desde' => $schema->string(),

            'fecha_hasta' => $schema->string(),

            'user_id' => $schema->integer()->min(1),

            'medio_pago' => $schema->string()
                ->enum([
                    'efectivo',
                    'transferencia',
                    'tarjeta',
                ]),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $datos = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'medio_pago' => ['nullable', 'in:efectivo,transferencia,tarjeta'],
        ]);

        if (! Gate::forUser($this->usuario)->allows('ver reportes')) {
            return 'El usuario no tiene permiso para consultar los reportes de ventas.';
        }

        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        $fechaActual = Carbon::now($zonaHoraria);

        $fechaDesde = isset($datos['fecha_desde'])
            ? Carbon::createFromFormat('Y-m-d', $datos['fecha_desde'], $zonaHoraria)
                ->startOfDay()
            : $fechaActual->copy()->startOfMonth();

        $fechaHasta = isset($datos['fecha_hasta'])
            ? Carbon::createFromFormat('Y-m-d', $datos['fecha_hasta'], $zonaHoraria)
                ->endOfDay()
            : $fechaActual->copy()->endOfDay();

        $inicioUtc = $fechaDesde->copy()->setTimezone('UTC');
        $finUtc = $fechaHasta->copy()->setTimezone('UTC');

        $consultaBase = Venta::query()
            ->whereBetween('created_at', [$inicioUtc, $finUtc]);

        if (isset($datos['user_id'])) {
            $consultaBase->where('user_id', $datos['user_id']);
        }

        if (isset($datos['medio_pago'])) {
            $consultaBase->where('medio_pago', $datos['medio_pago']);
        }

        $ventasCompletadas = (clone $consultaBase)
            ->where('estado', 'completada');

        $ventasAnuladas = (clone $consultaBase)
            ->where('estado', 'anulada');

        $cantidadVentas = (clone $ventasCompletadas)->count();

        $totalVendido = (clone $ventasCompletadas)->sum('total');

        $cantidadAnuladas = (clone $ventasAnuladas)->count();

        $mediosPago = [];

        foreach (['efectivo', 'transferencia', 'tarjeta'] as $medio) {
            $mediosPago[$medio] = (clone $ventasCompletadas)
                ->where('medio_pago', $medio)
                ->sum('total');
        }

        $ventasPeriodo = (clone $ventasCompletadas)
            ->get(['created_at', 'total']);

        $agruparPorMes = $fechaDesde->diffInDays($fechaHasta) > 92;

        $serieTemporal = $ventasPeriodo
            ->groupBy(function (Venta $venta) use ($zonaHoraria, $agruparPorMes): string {
                $fecha = $venta->created_at->copy()->timezone($zonaHoraria);

                return $agruparPorMes
                    ? $fecha->format('Y-m')
                    : $fecha->format('Y-m-d');
            })
            ->map(fn ($ventas) => $ventas->sum('total'))
            ->sortKeys();

        $etiquetas = [];
        $valores = [];

        foreach ($serieTemporal as $periodo => $total) {
            $fecha = Carbon::parse($periodo, $zonaHoraria);

            $etiquetas[] = $agruparPorMes
                ? $fecha->format('m/Y')
                : $fecha->format('d/m/Y');

            $valores[] = $total;
        }

        return json_encode([
            'periodo' => [
                'fecha_desde' => $fechaDesde->format('Y-m-d'),
                'fecha_hasta' => $fechaHasta->format('Y-m-d'),
            ],

            'filtros' => [
                'user_id' => $datos['user_id'] ?? null,
                'medio_pago' => $datos['medio_pago'] ?? null,
            ],

            'resumen' => [
                'cantidad_ventas_completadas' => $cantidadVentas,
                'total_vendido' => $totalVendido,
                'cantidad_ventas_anuladas' => $cantidadAnuladas,
            ],

            'ventas_por_medio_pago' => $mediosPago,

            'evolucion_ventas' => [
                'agrupacion' => $agruparPorMes ? 'mensual' : 'diaria',
                'etiquetas' => $etiquetas,
                'valores' => $valores,
            ],
        ], JSON_UNESCAPED_UNICODE)
            ?: 'No se pudieron procesar los resultados.';
    }
}