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

class ConsultarVentas implements Tool
{
    public function __construct(
        private readonly User $usuario
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta ventas registradas en Casa Yacobone. Permite filtrar por fecha, estado, medio de pago y monto. Incluye los productos vendidos, cantidades y precios historicos. Los administradores pueden consultar todas las ventas y los vendedores solamente las propias. Es una herramienta de solo lectura.';
    }

    public function schema(JsonSchema $schema): array
    {
        $campos = [
            'fecha' => $schema->string(),

            'fecha_desde' => $schema->string(),

            'fecha_hasta' => $schema->string(),

            'estado' => $schema->string()
                ->enum(['completada', 'anulada', 'todos']),

            'medio_pago' => $schema->string()
                ->enum(['efectivo', 'transferencia', 'tarjeta', 'todos']),

            'monto_min' => $schema->number(),

            'monto_max' => $schema->number(),

            'limite' => $schema->integer()
                ->description('Usa 10 como limite cuando el usuario no especifique una cantidad. Utiliza mas de 10 solamente cuando el usuario solicite expresamente mas resultados.')
                ->min(1)
                ->max(20),
        ];

        if ($this->usuario->isAdmin()) {
            $campos['usuario_id'] = $schema->integer()->min(1);
        }

        return $campos;
    }

    public function handle(Request $request): Stringable|string
    {
        $reglas = [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'in:completada,anulada,todos'],
            'medio_pago' => ['nullable', 'in:efectivo,transferencia,tarjeta,todos'],
            'monto_min' => ['nullable', 'numeric', 'min:0'],
            'monto_max' => ['nullable', 'numeric', 'min:0'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        if ($this->usuario->isAdmin()) {
            $reglas['usuario_id'] = ['nullable', 'integer', 'min:1'];
        }

        $datos = $request->validate($reglas);

        if (! Gate::forUser($this->usuario)->allows('viewAny', Venta::class)) {
            return 'El usuario no tiene permiso para consultar ventas.';
        }

        $esAdministrador = $this->usuario->isAdmin();

        if (! $esAdministrador && isset($datos['usuario_id'])) {
            return 'No tenes permiso para consultar las ventas de otros usuarios.';
        }

        if (
            isset($datos['fecha_desde'], $datos['fecha_hasta'])
            && $datos['fecha_hasta'] < $datos['fecha_desde']
        ) {
            return 'La fecha hasta no puede ser anterior a la fecha desde.';
        }

        if (
            isset($datos['monto_min'], $datos['monto_max'])
            && $datos['monto_min'] > $datos['monto_max']
        ) {
            return 'El monto minimo no puede ser mayor que el monto maximo.';
        }

        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        $consulta = Venta::query()
            ->select([
                'id',
                'user_id',
                'total',
                'medio_pago',
                'estado',
                'created_at',
            ]);

        // Los vendedores solamente consultan sus propias ventas.
        if (! $esAdministrador) {
            $consulta->where('user_id', $this->usuario->id);
        } elseif (isset($datos['usuario_id'])) {
            $consulta->where('user_id', $datos['usuario_id']);
        }

        // Fecha exacta.
        if (isset($datos['fecha'])) {
            $inicio = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha'],
                $zonaHoraria
            )->startOfDay()->setTimezone('UTC');

            $fin = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha'],
                $zonaHoraria
            )->endOfDay()->setTimezone('UTC');

            $consulta->whereBetween('created_at', [$inicio, $fin]);
        }

        // Fecha desde.
        if (isset($datos['fecha_desde'])) {
            $desde = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha_desde'],
                $zonaHoraria
            )->startOfDay()->setTimezone('UTC');

            $consulta->where('created_at', '>=', $desde);
        }

        // Fecha hasta.
        if (isset($datos['fecha_hasta'])) {
            $hasta = Carbon::createFromFormat(
                'Y-m-d',
                $datos['fecha_hasta'],
                $zonaHoraria
            )->endOfDay()->setTimezone('UTC');

            $consulta->where('created_at', '<=', $hasta);
        }

        if (
            isset($datos['estado'])
            && $datos['estado'] !== 'todos'
        ) {
            $consulta->where('estado', $datos['estado']);
        }

        if (
            isset($datos['medio_pago'])
            && $datos['medio_pago'] !== 'todos'
        ) {
            $consulta->where('medio_pago', $datos['medio_pago']);
        }

        if (isset($datos['monto_min'])) {
            $consulta->where('total', '>=', $datos['monto_min']);
        }

        if (isset($datos['monto_max'])) {
            $consulta->where('total', '<=', $datos['monto_max']);
        }

        $total = (clone $consulta)->count();

        // Solo cargamos los campos necesarios para responder.
        $relaciones = [
            'detalles' => function ($query) {
                $query->select([
                    'id',
                    'venta_id',
                    'producto_id',
                    'cantidad',
                    'precio_unitario',
                    'subtotal',
                ])->with('producto:id,nombre');
            },
        ];

        if ($esAdministrador) {
            $relaciones['user'] = function ($query) {
                $query->select(['id', 'name']);
            };
        }

        $ventas = $consulta
            ->with($relaciones)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($datos['limite'] ?? 10)
            ->get();

        $resultados = $ventas->map(function (Venta $venta) use (
            $esAdministrador,
            $zonaHoraria
        ): array {
            $resultado = [
                'id' => $venta->id,
                'fecha' => $venta->created_at
                    ->copy()
                    ->timezone($zonaHoraria)
                    ->format('d/m/Y H:i'),
                'estado' => $venta->estado,
                'medio_pago' => $venta->medio_pago,
                'total' => $venta->total,
                'productos' => $venta->detalles->map(
                    function ($detalle): array {
                        return [
                            'nombre' => $detalle->producto?->nombre
                                ?? 'Producto no disponible',
                            'cantidad' => $detalle->cantidad,
                            'precio_unitario' => $detalle->precio_unitario,
                            'subtotal' => $detalle->subtotal,
                        ];
                    }
                )->values()->all(),
            ];

            if ($esAdministrador) {
                $resultado['vendedor'] = $venta->user?->name
                    ?? 'Usuario no disponible';
            }

            return $resultado;
        })->values()->all();

        return json_encode([
            'alcance_consulta' => $esAdministrador
                ? 'todas_las_ventas'
                : 'ventas_del_usuario_autenticado',

            'descripcion_alcance' => $esAdministrador
                ? 'La consulta incluye las ventas de todos los usuarios.'
                : 'La consulta incluye exclusivamente las ventas propias del vendedor autenticado. No representa el total general del negocio.',

            'total_encontradas' => $total,
            'cantidad_devuelta' => count($resultados),
            'ventas' => $resultados,
        ], JSON_UNESCAPED_UNICODE)
            ?: 'No se pudieron procesar los resultados.';
    }
}