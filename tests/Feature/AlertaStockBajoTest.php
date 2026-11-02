<?php

use App\Mail\StockBajoMail;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// Avisos de stock bajo por mail: se disparan cuando una operación deja un
// producto en su stock mínimo o por debajo, y sólo en ese cruce.

/**
 * Crea un administrador con un mail real (los dominios .test se descartan).
 */
function adminConMail(string $email = 'duenio@yapeyu.com'): User
{
    $admin = User::factory()->create(['email' => $email]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * Crea un vendedor con la caja abierta para poder registrar ventas.
 */
function vendedorConCaja(): User
{
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');
    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    return $vendedor;
}

/**
 * Registra una venta de una unidad del producto.
 */
function vender(mixed $test, User $vendedor, Producto $producto, int $cantidad = 1): void
{
    $test->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => $cantidad],
        ],
    ])->assertRedirect();
}

test('avisa por mail al admin cuando una venta deja el producto en su stock mínimo', function () {
    Mail::fake();

    adminConMail();
    $vendedor = vendedorConCaja();

    $producto = Producto::factory()->create([
        'nombre' => 'Martillo Galponero',
        'stock' => 6,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    vender($this, $vendedor, $producto, 2);

    expect($producto->fresh()->stock)->toBe(4);

    Mail::assertSent(StockBajoMail::class, function (StockBajoMail $mail) {
        return $mail->hasTo('duenio@yapeyu.com')
            && $mail->motivo === 'Venta #1'
            && count($mail->productos) === 1
            && $mail->productos[0]['nombre'] === 'Martillo Galponero'
            && $mail->productos[0]['stock'] === 4
            && $mail->productos[0]['stock_minimo'] === 5;
    });
});

test('avisa a todos los administradores en un mismo mail', function () {
    Mail::fake();

    adminConMail('duenio@yapeyu.com');
    adminConMail('encargado@yapeyu.com');
    $vendedor = vendedorConCaja();

    $producto = Producto::factory()->create(['stock' => 3, 'stock_minimo' => 2, 'activo' => true]);

    vender($this, $vendedor, $producto, 2);

    Mail::assertSentCount(1);
    Mail::assertSent(StockBajoMail::class, function (StockBajoMail $mail) {
        return $mail->hasTo('duenio@yapeyu.com') && $mail->hasTo('encargado@yapeyu.com');
    });
});

test('no vuelve a avisar si el producto ya estaba en su stock mínimo', function () {
    Mail::fake();

    adminConMail();
    $vendedor = vendedorConCaja();

    // Ya está por debajo del mínimo antes de la venta.
    $producto = Producto::factory()->create(['stock' => 4, 'stock_minimo' => 5, 'activo' => true]);

    vender($this, $vendedor, $producto);

    expect($producto->fresh()->stock)->toBe(3);

    Mail::assertNothingSent();
});

test('no avisa cuando la venta no llega al stock mínimo', function () {
    Mail::fake();

    adminConMail();
    $vendedor = vendedorConCaja();

    $producto = Producto::factory()->create(['stock' => 10, 'stock_minimo' => 5, 'activo' => true]);

    vender($this, $vendedor, $producto, 2);

    Mail::assertNothingSent();
});

test('junta en un solo mail todos los productos que cruzaron el mínimo en la misma venta', function () {
    Mail::fake();

    adminConMail();
    $vendedor = vendedorConCaja();

    $primero = Producto::factory()->create(['nombre' => 'Clavos', 'stock' => 3, 'stock_minimo' => 2, 'activo' => true]);
    $segundo = Producto::factory()->create(['nombre' => 'Tornillos', 'stock' => 5, 'stock_minimo' => 4, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $primero->id, 'cantidad' => 2],
            ['id' => $segundo->id, 'cantidad' => 2],
        ],
    ])->assertRedirect();

    Mail::assertSentCount(1);
    Mail::assertSent(StockBajoMail::class, function (StockBajoMail $mail) {
        return count($mail->productos) === 2
            && collect($mail->productos)->pluck('nombre')->all() === ['Clavos', 'Tornillos'];
    });
});

test('avisa cuando un descarte deja el producto en su stock mínimo', function () {
    Mail::fake();

    $admin = adminConMail();

    $producto = Producto::factory()->create([
        'nombre' => 'Pintura Vencida',
        'stock' => 6,
        'stock_minimo' => 3,
        'activo' => true,
    ]);

    $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 4,
        'motivo' => 'Lata vencida y oxidada',
    ])->assertRedirect();

    expect($producto->fresh()->stock)->toBe(2);

    Mail::assertSent(StockBajoMail::class, function (StockBajoMail $mail) {
        return str_starts_with($mail->motivo, 'Descarte #')
            && $mail->productos[0]['nombre'] === 'Pintura Vencida';
    });
});

test('no avisa cuando se ingresa mercadería', function () {
    Mail::fake();

    $admin = adminConMail();

    $producto = Producto::factory()->create(['stock' => 0, 'stock_minimo' => 5, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 10,
    ])->assertRedirect();

    Mail::assertNothingSent();
});

test('la venta se registra igual si el envío del mail falla', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));

    adminConMail();
    $vendedor = vendedorConCaja();

    $producto = Producto::factory()->create(['stock' => 3, 'stock_minimo' => 2, 'activo' => true]);

    vender($this, $vendedor, $producto, 2);

    expect($producto->fresh()->stock)->toBe(1)
        ->and(App\Models\Venta::count())->toBe(1);
});

test('no intenta enviar a direcciones de prueba', function () {
    Mail::fake();

    $admin = User::factory()->create(['email' => 'admin@local.test']);
    $admin->assignRole('admin');

    $vendedor = vendedorConCaja();

    $producto = Producto::factory()->create(['stock' => 2, 'stock_minimo' => 1, 'activo' => true]);

    vender($this, $vendedor, $producto);

    Mail::assertNothingSent();
});
