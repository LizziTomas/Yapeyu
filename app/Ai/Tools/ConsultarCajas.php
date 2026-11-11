<?php

namespace App\Ai\Tools;

use App\Models\Caja;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConsultarCajas implements Tool
{
    public function __construct(
        private readonly User $usuario
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta cajas registradas en Casa Yacobone. Permite filtrar por estado. Devuelve fechas, montos y totales de ventas por medio de pago. Los administradores pueden consultar todas las cajas y los vendedores solamente las propias. Es una herramienta de solo lectura.';
    }

    public function schema(JsonSchema $schema): array
    {
        $campos = [
            'estado' => $schema->string()
                ->enum(['abierta', 'cerrada', 'todos']),

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
            'estado' => ['nullable', 'in:abierta,cerrada,todos'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        if ($this->usuario->isAdmin()) {
            $reglas['usuario_id'] = [
                'nullable',
                'integer',
                'min:1',
                'exists:users,id',
            ];
        }

        $datos = $request->validate($reglas);

        if (! Gate::forUser($this->usuario)->allows('viewAny', Caja::class)) {
            return 'El usuario no tiene permiso para consultar cajas.';
        }

        $esAdministrador = $this->usuario->isAdmin();
        $situacionCajaNegocio = null;
        if (! $esAdministrador) {
            $cajaPropiaAbierta = $this->usuario->cajaAbierta();
            $cajaDeOtro = $this->usuario->cajaAbiertaDeOtro();

            $nombreOtroUsuario = $cajaDeOtro?->user?->name;

            if ($cajaPropiaAbierta !== null) {
                $situacionCajaNegocio = [
                    'estado' => 'propia_abierta',
                    'tiene_caja_propia_abierta' => true,
                    'hay_otra_caja_abierta' => $cajaDeOtro !== null,
                    'usuario_que_tiene_otra_caja_abierta' => null,
                    'mensaje' => 'El usuario autenticado tiene una caja abierta.',
                ];
            } elseif ($cajaDeOtro !== null) {
                $situacionCajaNegocio = [
                    'estado' => 'otra_abierta',
                    'tiene_caja_propia_abierta' => false,
                    'hay_otra_caja_abierta' => true,
                    'usuario_que_tiene_otra_caja_abierta' => $nombreOtroUsuario,
                    'mensaje' => $nombreOtroUsuario
                        ? "{$nombreOtroUsuario} tiene una caja abierta. No se puede abrir otra hasta que se cierre."
                        : 'Hay otra caja abierta en el negocio. No se puede abrir otra hasta que se cierre.',
                ];
            } else {
                $situacionCajaNegocio = [
                    'estado' => 'ninguna_abierta',
                    'tiene_caja_propia_abierta' => false,
                    'hay_otra_caja_abierta' => false,
                    'usuario_que_tiene_otra_caja_abierta' => null,
                    'mensaje' => 'No hay ninguna caja abierta en el negocio.',
                ];
            }
        }

        $consulta = Caja::query();

        // Los vendedores solo pueden consultar sus propias cajas.
        if (! $esAdministrador) {
            $consulta->where('user_id', $this->usuario->id);
        } elseif (isset($datos['usuario_id'])) {
            $consulta->where('user_id', $datos['usuario_id']);
        }

        $estado = $datos['estado'] ?? 'todos';

        if ($estado !== 'todos') {
            $consulta->where('estado', $estado);
        }

        $total = (clone $consulta)->count();

        $consulta->select([
            'id',
            'user_id',
            'fecha_apertura',
            'fecha_cierre',
            'monto_inicial',
            'monto_esperado',
            'monto_real',
            'diferencia',
            'estado',
        ]);

        // Calculamos los totales sin cargar todas las ventas de cada caja.
        $consulta->withSum([
            'ventas as total_ventas_efectivo' => fn ($query) => $query
                ->where('estado', 'completada')
                ->where('medio_pago', 'efectivo'),

            'ventas as total_ventas_transferencia' => fn ($query) => $query
                ->where('estado', 'completada')
                ->where('medio_pago', 'transferencia'),

            'ventas as total_ventas_tarjeta' => fn ($query) => $query
                ->where('estado', 'completada')
                ->where('medio_pago', 'tarjeta'),

            'ventas as total_vendido' => fn ($query) => $query
                ->where('estado', 'completada'),
        ], 'total');

        if ($esAdministrador) {
            $consulta->with('user:id,name');
        }

        $cajas = $consulta
            ->orderByDesc('fecha_apertura')
            ->limit($datos['limite'] ?? 10)
            ->get();

        $zonaHoraria = 'America/Argentina/Buenos_Aires';

        $resultados = $cajas->map(
            function (Caja $caja) use (
                $esAdministrador,
                $zonaHoraria
            ): array {
                $ventasEfectivo = (float) ($caja->total_ventas_efectivo ?? 0);
                $ventasTransferencia = (float) ($caja->total_ventas_transferencia ?? 0);
                $ventasTarjeta = (float) ($caja->total_ventas_tarjeta ?? 0);

                // Respeta el cálculo existente del modelo Caja.
                if (
                    $caja->estaCerrada()
                    && $caja->monto_esperado !== null
                ) {
                    $dineroEsperado = (float) $caja->monto_esperado;
                } else {
                    $dineroEsperado = (float) $caja->monto_inicial
                        + $ventasEfectivo;
                }

                $resultado = [
                    'id' => $caja->id,
                    'estado' => $caja->estado,

                    'fecha_apertura' => $caja->fecha_apertura
                        ?->copy()
                        ->timezone($zonaHoraria)
                        ->format('d/m/Y H:i'),

                    'fecha_cierre' => $caja->fecha_cierre
                        ?->copy()
                        ->timezone($zonaHoraria)
                        ->format('d/m/Y H:i'),

                    'monto_inicial' => (float) $caja->monto_inicial,
                    'ventas_efectivo' => $ventasEfectivo,
                    'ventas_transferencia' => $ventasTransferencia,
                    'ventas_tarjeta' => $ventasTarjeta,

                    'total_vendido' => (float) ($caja->total_vendido ?? 0),

                    'dinero_esperado' => round($dineroEsperado, 2),

                    'monto_real' => $caja->monto_real !== null
                        ? (float) $caja->monto_real
                        : null,

                    'diferencia' => $caja->diferencia !== null
                        ? (float) $caja->diferencia
                        : null,
                ];

                if ($esAdministrador) {
                    $resultado['usuario'] = $caja->user?->name
                        ?? 'Usuario no disponible';
                }

                return $resultado;
            }
        )->values()->all();

        return json_encode([
            'alcance_consulta' => $esAdministrador
                ? 'todas_las_cajas'
                : 'cajas_del_usuario_autenticado',

            'descripcion_alcance' => $esAdministrador
                ? 'La consulta incluye las cajas de todos los usuarios, salvo que se aplique un filtro por usuario.'
                : 'La consulta incluye exclusivamente las cajas propias del usuario autenticado.',

            'situacion_caja_negocio' => $situacionCajaNegocio,

            'total_encontradas' => $total,
            'cantidad_devuelta' => count($resultados),
            'cajas' => $resultados,
        ], JSON_UNESCAPED_UNICODE)
            ?: 'No se pudieron procesar los resultados.';
    }
}