<?php

namespace App\Ai\Tools;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConsultarProductos implements Tool
{
    public function __construct(
        private readonly User $usuario
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta productos del sistema. Permite buscar por nombre, filtrar por estado y nivel de stock. Respeta los permisos del usuario y no modifica informacion.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'busqueda' => $schema->string(),

            'estado' => $schema->string()
                ->enum(['activos', 'inactivos', 'todos']),

            'stock' => $schema->string()
                ->enum(['sin_stock', 'bajo', 'normal', 'todos']),

            'limite' => $schema->integer()
            ->description('Usa 10 como limite cuando el usuario no especifique una cantidad. Utiliza mas de 10 solamente cuando el usuario solicite expresamente mas resultados.')
            ->min(1)
            ->max(20),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $datos = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activos,inactivos,todos'],
            'stock' => ['nullable', 'in:sin_stock,bajo,normal,todos'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if (! Gate::forUser($this->usuario)->allows('viewAny', Producto::class)) {
            return 'El usuario no tiene permiso para consultar productos.';
        }

        $consulta = Producto::query();

        $busqueda = trim($datos['busqueda'] ?? '');

        if ($busqueda !== '') {
            $consulta->where('nombre', 'like', "%{$busqueda}%");
        }

        $estado = $datos['estado'] ?? 'activos';

        if ($estado === 'activos') {
            $consulta->where('activo', true);
        } elseif ($estado === 'inactivos') {
            $consulta->where('activo', false);
        }

        $stock = $datos['stock'] ?? 'todos';

        if ($stock === 'sin_stock') {
            $consulta->where('stock', '<=', 0);
        } elseif ($stock === 'bajo') {
            $consulta->where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'stock_minimo');
        } elseif ($stock === 'normal') {
            $consulta->whereColumn('stock', '>', 'stock_minimo');
        }

        $total = (clone $consulta)->count();

        $limite = $datos['limite'] ?? 10;

        $columnas = [
            'id',
            'nombre',
            'precio_venta',
            'stock',
            'stock_minimo',
            'activo',
        ];

        $esAdministrador = $this->usuario->isAdmin();

        if ($esAdministrador) {
            $columnas[] = 'precio_costo';
        }

        $productos = $consulta
            ->select($columnas)
            ->orderBy('nombre')
            ->limit($limite)
            ->get()
            ->map(function (Producto $producto) use ($esAdministrador): array {
                $resultado = [
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'precio_venta' => $producto->precio_venta,
                    'stock' => $producto->stock,
                    'stock_minimo' => $producto->stock_minimo,
                    'activo' => $producto->activo,
                ];

                if ($esAdministrador) {
                    $resultado['precio_costo'] = $producto->precio_costo;
                }

                return $resultado;
            });

        return json_encode([
            'total_encontrados' => $total,
            'cantidad_devuelta' => $productos->count(),
            'productos' => $productos->values()->all(),
        ], JSON_UNESCAPED_UNICODE)
            ?: 'No se pudieron procesar los resultados.';
    }
}