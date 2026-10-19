<?php

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;

test('usuarios no autenticados son redirigidos al login al acceder al descarte', function () {
    $this->get(route('movimientos-stock.descartar'))->assertRedirect(route('login'));
    $this->post(route('movimientos-stock.descartar.store'), [])->assertRedirect(route('login'));
});

test('un admin puede acceder al formulario de descarte', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('movimientos-stock.descartar'))->assertStatus(200);
});

test('un admin puede registrar un descarte y el stock disminuye sin generar una venta', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create([
        'nombre' => 'Vaso de vidrio',
        'precio_costo' => 100.00,
        'precio_venta' => 200.00,
        'stock' => 10,
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 3,
        'motivo' => 'Se rompieron al caer del estante',
    ]);

    $movimiento = MovimientoStock::where('tipo', MovimientoStock::TIPO_DESCARTE)->first();
    expect($movimiento)->not->toBeNull();

    $response->assertRedirect(route('movimientos-stock.show', $movimiento));
    $response->assertSessionHas('success');

    expect($producto->refresh()->stock)->toBe(7);

    expect($movimiento->cantidad)->toBe(3)
        ->and($movimiento->stock_anterior)->toBe(10)
        ->and($movimiento->stock_posterior)->toBe(7)
        ->and($movimiento->observacion)->toBe('Se rompieron al caer del estante')
        ->and($movimiento->user_id)->toBe($admin->id);

    // Un descarte no genera ninguna venta
    $this->assertDatabaseCount('ventas', 0);
});

test('no se puede descartar más stock del disponible', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create(['stock' => 5, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 6,
        'motivo' => 'Intento de descarte mayor al stock',
    ])->assertSessionHasErrors('cantidad');

    expect($producto->refresh()->stock)->toBe(5)
        ->and(MovimientoStock::where('tipo', MovimientoStock::TIPO_DESCARTE)->count())->toBe(0);
});

test('no se puede descartar mercadería de un producto inactivo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->inactivo()->create(['stock' => 10]);

    $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'motivo' => 'Producto inactivo descartado',
    ])->assertSessionHasErrors('producto_id');

    expect($producto->refresh()->stock)->toBe(10)
        ->and(MovimientoStock::where('tipo', MovimientoStock::TIPO_DESCARTE)->count())->toBe(0);
});

test('el motivo del descarte es obligatorio', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create(['stock' => 10, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'motivo' => '',
    ])->assertSessionHasErrors('motivo');

    expect($producto->refresh()->stock)->toBe(10);
});

test('un vendedor no puede registrar descartes', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $producto = Producto::factory()->create(['stock' => 10, 'activo' => true]);

    $this->actingAs($vendedor)
        ->get(route('movimientos-stock.descartar'))
        ->assertStatus(403);

    $this->actingAs($vendedor)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'motivo' => 'Intento de descarte por vendedor',
    ])->assertStatus(403);

    expect($producto->refresh()->stock)->toBe(10)
        ->and(MovimientoStock::where('tipo', MovimientoStock::TIPO_DESCARTE)->count())->toBe(0);
});
