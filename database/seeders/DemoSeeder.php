<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Genera datos de demostración realistas para presentar el sistema:
 * usuarios, catálogo de productos, cajas históricas con ventas, descartes,
 * anulaciones y una caja abierta del día.
 *
 * Es idempotente: si ya existen ventas registradas, no vuelve a cargar nada.
 */
class DemoSeeder extends Seeder
{
    /**
     * Zona horaria oficial del negocio.
     */
    protected string $zonaHoraria = 'America/Argentina/Buenos_Aires';

    /**
     * Motivos utilizados en las ventas anuladas de ejemplo.
     *
     * @var array<int, string>
     */
    protected array $motivosAnulacion = [
        'Cobro duplicado por error en el sistema',
        'El cliente devolvió la mercadería',
        'Venta cargada por duplicado',
        'Error en la selección del medio de pago',
    ];

    /**
     * Motivos utilizados en los descartes de mercadería de ejemplo.
     *
     * @var array<int, string>
     */
    protected array $motivosDescarte = [
        'Mercadería dañada por humedad',
        'Producto vencido',
        'Se rompió durante la exhibición',
        'Envase golpeado en el transporte',
        'Merma por manipulación',
    ];

    /**
     * Ejecuta la carga de datos de demostración.
     */
    public function run(): void
    {
        if (Venta::exists()) {
            $this->command?->warn('Ya existen ventas registradas: se omite la carga de datos de demostración.');
            return;
        }

        // Semilla fija para que la demostración sea siempre reproducible
        fake()->seed(20261019);

        $this->command?->info('Generando datos de demostración...');

        $admin = $this->crearUsuario('Administrador', 'admin@local.test', 'admin123', 'admin');

        $vendedores = collect([
            $this->crearUsuario('Lucía Fernández', 'vendedor1@local.test', 'vendedor123', 'vendedor'),
            $this->crearUsuario('Martín Gómez', 'vendedor2@local.test', 'vendedor123', 'vendedor'),
            $this->crearUsuario('Sofía Ramírez', 'vendedor3@local.test', 'vendedor123', 'vendedor'),
        ]);

        $productos = $this->crearProductos($admin);

        // Cajas cerradas de los últimos 60 días con sus ventas
        for ($dias = 60; $dias >= 1; $dias--) {
            $dia = Carbon::now($this->zonaHoraria)->subDays($dias);

            // Entre 1 y 2 vendedores trabajan en el día
            $vendedoresDelDia = $vendedores->shuffle()->take(fake()->numberBetween(1, 2));

            foreach ($vendedoresDelDia as $vendedor) {
                if (fake()->boolean(78)) {
                    $this->crearCajaCerrada($vendedor, $admin, $productos, $dia);
                }
            }
        }

        // Descartes de mercadería (los registra el administrador)
        $this->crearDescartes($admin, $productos);

        // Caja abierta del día para poder demostrar ventas en vivo
        $this->crearCajaAbierta($vendedores->first(), $productos);

        $this->command?->info('Datos de demostración generados correctamente.');
    }

    /**
     * Crea (o recupera) un usuario y le asigna su rol.
     */
    protected function crearUsuario(string $nombre, string $email, string $password, string $rol): User
    {
        $usuario = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => Hash::make($password)]
        );

        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * Crea el catálogo de productos con su movimiento de carga inicial de stock.
     *
     * @return Collection<int, Producto>
     */
    protected function crearProductos(User $admin): Collection
    {
        $definiciones = [
            ['nombre' => 'Destornillador Phillips 6"', 'costo' => 1200, 'venta' => 2100, 'stock' => 24, 'minimo' => 5, 'activo' => true],
            ['nombre' => 'Juego de llaves combinadas x8', 'costo' => 8500, 'venta' => 14900, 'stock' => 12, 'minimo' => 3, 'activo' => true],
            ['nombre' => 'Martillo de carpintero 27mm', 'costo' => 4200, 'venta' => 7300, 'stock' => 15, 'minimo' => 4, 'activo' => true],
            ['nombre' => 'Cinta métrica 5m', 'costo' => 2100, 'venta' => 3800, 'stock' => 30, 'minimo' => 8, 'activo' => true],
            ['nombre' => 'Taladro percutor 650W', 'costo' => 62000, 'venta' => 98000, 'stock' => 6, 'minimo' => 2, 'activo' => true],
            ['nombre' => 'Juego de mechas x10', 'costo' => 5400, 'venta' => 8900, 'stock' => 18, 'minimo' => 5, 'activo' => true],
            ['nombre' => 'Alicate universal 8"', 'costo' => 3600, 'venta' => 6200, 'stock' => 16, 'minimo' => 4, 'activo' => true],
            ['nombre' => 'Llave inglesa 10"', 'costo' => 4800, 'venta' => 8200, 'stock' => 10, 'minimo' => 3, 'activo' => true],
            ['nombre' => 'Sierra manual 22"', 'costo' => 3200, 'venta' => 5600, 'stock' => 8, 'minimo' => 2, 'activo' => true],
            ['nombre' => 'Lámpara LED 9W', 'costo' => 900, 'venta' => 1700, 'stock' => 60, 'minimo' => 15, 'activo' => true],
            ['nombre' => 'Cable unipolar 2,5mm x10m', 'costo' => 4500, 'venta' => 7800, 'stock' => 20, 'minimo' => 6, 'activo' => true],
            ['nombre' => 'Tomacorriente doble', 'costo' => 1100, 'venta' => 2200, 'stock' => 40, 'minimo' => 10, 'activo' => true],
            ['nombre' => 'Cinta aisladora', 'costo' => 350, 'venta' => 700, 'stock' => 100, 'minimo' => 20, 'activo' => true],
            ['nombre' => 'Set de destornilladores x6', 'costo' => 6800, 'venta' => 11500, 'stock' => 14, 'minimo' => 4, 'activo' => true],
            ['nombre' => 'Guantes de trabajo reforzados', 'costo' => 1500, 'venta' => 2800, 'stock' => 35, 'minimo' => 10, 'activo' => true],
            ['nombre' => 'Candado de seguridad 40mm', 'costo' => 2900, 'venta' => 5200, 'stock' => 22, 'minimo' => 6, 'activo' => true],
            ['nombre' => 'Bisagra 3" reforzada', 'costo' => 800, 'venta' => 1600, 'stock' => 50, 'minimo' => 12, 'activo' => true],
            ['nombre' => 'Cerradura de embutir', 'costo' => 5200, 'venta' => 8900, 'stock' => 9, 'minimo' => 3, 'activo' => true],
            ['nombre' => 'Pegamento instantáneo', 'costo' => 600, 'venta' => 1200, 'stock' => 70, 'minimo' => 15, 'activo' => true],
            ['nombre' => 'Silicona transparente', 'costo' => 1400, 'venta' => 2600, 'stock' => 25, 'minimo' => 8, 'activo' => true],
            ['nombre' => 'Cuchillo criollo 20cm', 'costo' => 7200, 'venta' => 12500, 'stock' => 11, 'minimo' => 3, 'activo' => true],
            ['nombre' => 'Vaso de vidrio x6', 'costo' => 2800, 'venta' => 4900, 'stock' => 18, 'minimo' => 5, 'activo' => true],
            ['nombre' => 'Termo de acero 1L', 'costo' => 9500, 'venta' => 16900, 'stock' => 7, 'minimo' => 2, 'activo' => true],
            ['nombre' => 'Set de tazas x4', 'costo' => 4600, 'venta' => 8400, 'stock' => 3, 'minimo' => 4, 'activo' => true],
            ['nombre' => 'Escoba de plástico', 'costo' => 2100, 'venta' => 3900, 'stock' => 2, 'minimo' => 5, 'activo' => true],
            ['nombre' => 'Cinta de embalar', 'costo' => 500, 'venta' => 1000, 'stock' => 45, 'minimo' => 10, 'activo' => true],
            ['nombre' => 'Batería 9V', 'costo' => 1800, 'venta' => 3200, 'stock' => 28, 'minimo' => 8, 'activo' => true],
            ['nombre' => 'Linterna LED recargable', 'costo' => 6200, 'venta' => 10900, 'stock' => 5, 'minimo' => 2, 'activo' => true],
            ['nombre' => 'Mochila portaherramientas', 'costo' => 14500, 'venta' => 24900, 'stock' => 4, 'minimo' => 2, 'activo' => true],
            ['nombre' => 'Alargue 3 tomas 5m', 'costo' => 5600, 'venta' => 9900, 'stock' => 13, 'minimo' => 4, 'activo' => true],
            ['nombre' => 'Cinta de papel', 'costo' => 400, 'venta' => 800, 'stock' => 6, 'minimo' => 12, 'activo' => true],
            ['nombre' => 'Ventilador de pie (descontinuado)', 'costo' => 28000, 'venta' => 44000, 'stock' => 4, 'minimo' => 0, 'activo' => false],
            ['nombre' => 'Calentador eléctrico (descontinuado)', 'costo' => 45000, 'venta' => 72000, 'stock' => 0, 'minimo' => 0, 'activo' => false],
        ];

        return collect($definiciones)->map(function (array $datos) use ($admin) {
            $producto = Producto::firstOrCreate(
                ['nombre' => $datos['nombre']],
                [
                    'precio_costo' => $datos['costo'],
                    'precio_venta' => $datos['venta'],
                    'stock' => $datos['stock'],
                    'stock_minimo' => $datos['minimo'],
                    'activo' => $datos['activo'],
                ]
            );

            // Movimiento de carga inicial de stock (ingreso)
            if ($producto->wasRecentlyCreated && $producto->stock > 0) {
                $fecha = Carbon::now($this->zonaHoraria)
                    ->subDays(fake()->numberBetween(70, 95))
                    ->setTime(fake()->numberBetween(8, 11), fake()->numberBetween(0, 59))
                    ->setTimezone('UTC');

                $this->registrarMovimiento(
                    $producto,
                    $admin,
                    MovimientoStock::TIPO_INGRESO,
                    (int) $producto->stock,
                    0,
                    (int) $producto->stock,
                    'Carga inicial de mercadería',
                    $fecha
                );
            }

            return $producto;
        });
    }

    /**
     * Crea una caja cerrada para un día con sus ventas y su arqueo final.
     *
     * @param  Collection<int, Producto>  $productos
     */
    protected function crearCajaCerrada(User $vendedor, User $admin, Collection $productos, Carbon $dia): void
    {
        $fechaApertura = $dia->copy()->setTime(9, 0)->setTimezone('UTC');
        $fechaCierre = $dia->copy()->setTime(18, 0)->setTimezone('UTC');

        $montoInicial = fake()->randomElement([5000, 8000, 10000, 12000, 15000]);

        $caja = Caja::create([
            'user_id' => $vendedor->id,
            'fecha_apertura' => $fechaApertura,
            'monto_inicial' => $montoInicial,
            'monto_esperado' => $montoInicial,
            'estado' => 'abierta',
        ]);

        $caja->created_at = $fechaApertura;
        $caja->updated_at = $fechaApertura;
        $caja->save();

        $cantidadVentas = fake()->numberBetween(1, 3);

        for ($i = 0; $i < $cantidadVentas; $i++) {
            $momento = $dia->copy()->setTime(fake()->numberBetween(9, 17), fake()->numberBetween(0, 59));
            $this->crearVenta($caja, $vendedor, $admin, $productos, $momento);
        }

        // Arqueo final: la mayoría de las cajas cierran exactas
        $esperado = round((float) $caja->monto_inicial + $caja->totalVentasEfectivo(), 2);
        $ajuste = fake()->randomElement([0, 0, 0, 0, -150, 150, -80, 250]);
        $real = round($esperado + $ajuste, 2);

        $caja->update([
            'fecha_cierre' => $fechaCierre,
            'monto_esperado' => $esperado,
            'monto_real' => $real,
            'diferencia' => round($real - $esperado, 2),
            'estado' => 'cerrada',
        ]);
    }

    /**
     * Crea una caja abierta del día con algunas ventas ya registradas.
     *
     * @param  Collection<int, Producto>  $productos
     */
    protected function crearCajaAbierta(User $vendedor, Collection $productos): void
    {
        $fechaApertura = Carbon::now($this->zonaHoraria)->setTime(9, 0)->setTimezone('UTC');
        $montoInicial = 10000;

        $caja = Caja::create([
            'user_id' => $vendedor->id,
            'fecha_apertura' => $fechaApertura,
            'monto_inicial' => $montoInicial,
            'monto_esperado' => $montoInicial,
            'estado' => 'abierta',
        ]);

        $caja->created_at = $fechaApertura;
        $caja->updated_at = $fechaApertura;
        $caja->save();

        for ($i = 0; $i < 3; $i++) {
            $momento = Carbon::now($this->zonaHoraria)->subMinutes(fake()->numberBetween(10, 240));
            $this->crearVenta($caja, $vendedor, $vendedor, $productos, $momento, false);
        }
    }

    /**
     * Crea una venta con sus renglones y movimientos de stock.
     *
     * @param  Collection<int, Producto>  $productos
     */
    protected function crearVenta(Caja $caja, User $vendedor, User $admin, Collection $productos, Carbon $momento, bool $permiteAnulacion = true): void
    {
        $disponibles = $productos->filter(fn (Producto $p) => $p->activo && $p->stock > 0);

        if ($disponibles->isEmpty()) {
            return;
        }

        $elegidos = $disponibles->shuffle()->take(fake()->numberBetween(1, 3));

        $fecha = $momento->copy()->setTimezone('UTC');

        $total = 0.0;
        $detalles = [];
        $movimientos = [];

        foreach ($elegidos as $producto) {
            $cantidad = min(fake()->numberBetween(1, 3), $producto->stock);

            if ($cantidad < 1) {
                continue;
            }

            $precio = (float) $producto->precio_venta;
            $costo = (float) $producto->precio_costo;
            $subtotal = round($precio * $cantidad, 2);

            $stockAnterior = (int) $producto->stock;
            $stockPosterior = $stockAnterior - $cantidad;

            $producto->update(['stock' => $stockPosterior]);

            $detalles[] = [
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'costo_unitario' => $costo,
                'subtotal' => $subtotal,
            ];

            $movimientos[] = [
                'producto' => $producto,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
            ];

            $total += $subtotal;
        }

        if (empty($detalles)) {
            return;
        }

        $venta = new Venta([
            'caja_id' => $caja->id,
            'user_id' => $vendedor->id,
            'total' => round($total, 2),
            'medio_pago' => fake()->randomElement(['efectivo', 'efectivo', 'efectivo', 'transferencia', 'transferencia', 'tarjeta']),
            'estado' => 'completada',
        ]);
        $venta->created_at = $fecha;
        $venta->updated_at = $fecha;
        $venta->save();

        foreach ($detalles as $detalle) {
            $venta->detalles()->create($detalle);
        }

        foreach ($movimientos as $movimiento) {
            $this->registrarMovimiento(
                $movimiento['producto'],
                $vendedor,
                MovimientoStock::TIPO_VENTA,
                $movimiento['cantidad'],
                $movimiento['stock_anterior'],
                $movimiento['stock_posterior'],
                "Venta #{$venta->id}",
                $fecha
            );
        }

        // Aproximadamente 4% de las ventas se anulan (para mostrar la auditoría)
        if ($permiteAnulacion && fake()->boolean(4)) {
            $this->anularVenta($venta, $movimientos, $admin, $fecha);
        }
    }

    /**
     * Anula una venta de ejemplo, estornando el stock y registrando el movimiento.
     *
     * @param  array<int, array{producto: Producto, cantidad: int, stock_anterior: int, stock_posterior: int}>  $movimientos
     */
    protected function anularVenta(Venta $venta, array $movimientos, User $admin, Carbon $fechaVenta): void
    {
        $motivo = fake()->randomElement($this->motivosAnulacion);
        $fechaAnulacion = $fechaVenta->copy()->addMinutes(fake()->numberBetween(10, 180));

        foreach ($movimientos as $movimiento) {
            /** @var Producto $producto */
            $producto = $movimiento['producto'];

            $stockAnterior = (int) $producto->stock;
            $stockPosterior = $stockAnterior + $movimiento['cantidad'];

            $producto->update(['stock' => $stockPosterior]);

            $this->registrarMovimiento(
                $producto,
                $admin,
                MovimientoStock::TIPO_ANULACION,
                $movimiento['cantidad'],
                $stockAnterior,
                $stockPosterior,
                "Anulación de la venta #{$venta->id}: {$motivo}",
                $fechaAnulacion
            );
        }

        $venta->update([
            'estado' => 'anulada',
            'motivo_anulacion' => $motivo,
            'anulada_por' => $admin->id,
            'anulada_at' => $fechaAnulacion,
        ]);
    }

    /**
     * Registra algunos descartes de mercadería repartidos en el tiempo.
     *
     * @param  Collection<int, Producto>  $productos
     */
    protected function crearDescartes(User $admin, Collection $productos): void
    {
        for ($i = 0; $i < 7; $i++) {
            $producto = $productos->filter(fn (Producto $p) => $p->activo && $p->stock > 0)->random();

            if (! $producto) {
                continue;
            }

            $cantidad = min(fake()->numberBetween(1, 2), (int) $producto->stock);
            $stockAnterior = (int) $producto->stock;
            $stockPosterior = $stockAnterior - $cantidad;

            $producto->update(['stock' => $stockPosterior]);

            $fecha = Carbon::now($this->zonaHoraria)
                ->subDays(fake()->numberBetween(1, 50))
                ->setTime(fake()->numberBetween(10, 17), fake()->numberBetween(0, 59))
                ->setTimezone('UTC');

            $this->registrarMovimiento(
                $producto,
                $admin,
                MovimientoStock::TIPO_DESCARTE,
                $cantidad,
                $stockAnterior,
                $stockPosterior,
                fake()->randomElement($this->motivosDescarte),
                $fecha
            );
        }
    }

    /**
     * Crea un movimiento de stock respetando una fecha histórica.
     */
    protected function registrarMovimiento(
        Producto $producto,
        User $usuario,
        string $tipo,
        int $cantidad,
        int $stockAnterior,
        int $stockPosterior,
        ?string $observacion,
        Carbon $fecha
    ): MovimientoStock {
        $movimiento = new MovimientoStock([
            'producto_id' => $producto->id,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_posterior' => $stockPosterior,
            'observacion' => $observacion,
        ]);

        $movimiento->created_at = $fecha;
        $movimiento->updated_at = $fecha;
        $movimiento->save();

        return $movimiento;
    }
}
