<?php

namespace App\Ai\Tools;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Illuminate\Database\Eloquent\Builder;

class ConsultarMovimientosStock implements Tool
{
    public function __construct(
        private readonly User $usuario
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta el historial de movimientos de stock de Casa Yacobone. Permite buscar por producto, usuario, tipo, fecha y observacion. Cuando se indica un producto y un periodo, tambien reconstruye el stock inicial y final, las entradas y las salidas. Es una herramienta de solo lectura. Las fechas deben expresarse como YYYY-MM-DD.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'producto_id' => $schema->integer()->min(1),

            'usuario_id' => $schema->integer()->min(1),

            'tipo' => $schema->string()
                ->enum([
                    'ingreso',
                    'venta',
                    'anulacion',
                    'descarte',
                    'todos',
                ]),

            'fecha' => $schema->string(),

            'fecha_desde' => $schema->string(),

            'fecha_hasta' => $schema->string(),

            'busqueda' => $schema->string(),

            'limite' => $schema->integer()
            ->description('Usa 10 como limite cuando el usuario no especifique una cantidad. Utiliza mas de 10 solamente cuando el usuario solicite expresamente mas resultados.')
            ->min(1)
            ->max(20),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $datos = $request->validate([
            'producto_id' => ['nullable', 'integer', 'min:1', 'exists:productos,id'],
            'usuario_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'tipo' => ['nullable', 'in:ingreso,venta,anulacion,descarte,todos'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'busqueda' => ['nullable', 'string', 'max:100'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if (! Gate::forUser($this->usuario)->allows('viewAny', MovimientoStock::class)) {
            return 'El usuario no tiene permiso para consultar movimientos de stock.';
        }

        if (
            isset($datos['fecha'])
            && (isset($datos['fecha_desde']) || isset($datos['fecha_hasta']))
        ) {
            return 'Utiliza fecha o un rango de fechas, pero no ambos al mismo tiempo.';
        }

        if (
            isset($datos['fecha_desde'], $datos['fecha_hasta'])
            && $datos['fecha_hasta'] < $datos['fecha_desde']
        ) {
            return 'La fecha hasta no puede ser anterior a la fecha desde.';
        }

        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        if (isset($datos['fecha'])) {
            $desde = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha'],
                $zonaHoraria
            )->startOfDay()->setTimezone('UTC');

            $hasta = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha'],
                $zonaHoraria
            )->endOfDay()->setTimezone('UTC');
        } else {
            $desde = isset($datos['fecha_desde'])
                ? Carbon::createFromFormat('Y-m-d', $datos['fecha_desde'], $zonaHoraria)
                    ->startOfDay()
                    ->setTimezone('UTC')
                : null;

            $hasta = isset($datos['fecha_hasta'])
                ? Carbon::createFromFormat('Y-m-d', $datos['fecha_hasta'], $zonaHoraria)
                    ->endOfDay()
                    ->setTimezone('UTC')
                : null;
        }

        $consulta = MovimientoStock::query();

        if (isset($datos['producto_id'])) {
            $consulta->where('producto_id', $datos['producto_id']);
        }

        if (isset($datos['usuario_id'])) {
            $consulta->where('user_id', $datos['usuario_id']);
        }

        $tipo = $datos['tipo'] ?? 'todos';

        if ($tipo !== 'todos') {
            $consulta->where('tipo', $tipo);
        }

        if (isset($datos['busqueda'])) {
            $busqueda = trim($datos['busqueda']);

            if ($busqueda !== '') {
                $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                    $subconsulta
                        ->where('observacion', 'like', "%{$busqueda}%")
                        ->orWhereHas('producto', function (Builder $producto) use ($busqueda) {
                            $producto->where('nombre', 'like', "%{$busqueda}%");
                        });
                });
            }
        }

        $this->aplicarRango($consulta, $desde, $hasta);

        $total = (clone $consulta)->count();

        $movimientos = $consulta
            ->select([
                'id',
                'producto_id',
                'user_id',
                'tipo',
                'cantidad',
                'stock_anterior',
                'stock_posterior',
                'observacion',
                'created_at',
            ])
            ->with([
                'producto:id,nombre',
                'user:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($datos['limite'] ?? 10)
            ->get();

        $resultados = $movimientos->map(
            fn (MovimientoStock $movimiento): array => [
                'id' => $movimiento->id,
                'producto' => $movimiento->producto?->nombre
                    ?? 'Producto no disponible',
                'usuario' => $movimiento->user?->name
                    ?? 'Usuario no disponible',
                'tipo' => $movimiento->tipo,
                'cantidad' => $movimiento->cantidad,
                'stock_anterior' => $movimiento->stock_anterior,
                'stock_posterior' => $movimiento->stock_posterior,
                'observacion' => $movimiento->observacion,
                'fecha' => $movimiento->created_at
                    ->copy()
                    ->timezone($zonaHoraria)
                    ->format('d/m/Y H:i'),
            ]
        )->values()->all();

        $resumenProducto = null;

        // Si se consulta un producto específico, reconstruimos su stock
        // al inicio y al final del periodo seleccionado.
        if (isset($datos['producto_id'])) {
            $producto = Producto::query()
                ->select(['id', 'nombre', 'stock'])
                ->find($datos['producto_id']);

            if ($producto) {
                $consultaPeriodo = MovimientoStock::query()
                    ->where('producto_id', $producto->id);

                $this->aplicarRango($consultaPeriodo, $desde, $hasta);

                $entradas = (int) (clone $consultaPeriodo)
                    ->whereIn('tipo', [
                        MovimientoStock::TIPO_INGRESO,
                        MovimientoStock::TIPO_ANULACION,
                    ])
                    ->sum('cantidad');

                $salidas = (int) (clone $consultaPeriodo)
                    ->whereIn('tipo', [
                        MovimientoStock::TIPO_VENTA,
                        MovimientoStock::TIPO_DESCARTE,
                    ])
                    ->sum('cantidad');

                $entradasPosteriores = 0;
                $salidasPosteriores = 0;

                if ($hasta) {
                    $movimientosPosteriores = MovimientoStock::query()
                        ->where('producto_id', $producto->id)
                        ->where('created_at', '>', $hasta);

                    $entradasPosteriores = (int) (clone $movimientosPosteriores)
                        ->whereIn('tipo', [
                            MovimientoStock::TIPO_INGRESO,
                            MovimientoStock::TIPO_ANULACION,
                        ])
                        ->sum('cantidad');

                    $salidasPosteriores = (int) (clone $movimientosPosteriores)
                        ->whereIn('tipo', [
                            MovimientoStock::TIPO_VENTA,
                            MovimientoStock::TIPO_DESCARTE,
                        ])
                        ->sum('cantidad');
                }

                $stockFinal = (int) $producto->stock
                    - ($entradasPosteriores - $salidasPosteriores);

                $stockInicial = $stockFinal - ($entradas - $salidas);

                $resumenProducto = [
                    'producto' => $producto->nombre,
                    'stock_actual' => (int) $producto->stock,
                    'stock_inicial_periodo' => $stockInicial,
                    'entradas_periodo' => $entradas,
                    'salidas_periodo' => $salidas,
                    'stock_final_periodo' => $stockFinal,
                ];
            }
        }

        return json_encode([
            'alcance_consulta' => 'historial_general_de_movimientos',

            'descripcion_alcance' => 'El usuario autenticado tiene permiso para consultar el historial general de movimientos de stock.',

            'total_encontrados' => $total,
            'cantidad_devuelta' => count($resultados),

            'resumen_producto' => $resumenProducto,

            'movimientos' => $resultados,
        ], JSON_UNESCAPED_UNICODE)
            ?: 'No se pudieron procesar los resultados.';
    }

    private function aplicarRango(
        Builder $consulta,
        ?Carbon $desde,
        ?Carbon $hasta
    ): Builder {
        if ($desde) {
            $consulta->where('created_at', '>=', $desde);
        }

        if ($hasta) {
            $consulta->where('created_at', '<=', $hasta);
        }

        return $consulta;
    }
}