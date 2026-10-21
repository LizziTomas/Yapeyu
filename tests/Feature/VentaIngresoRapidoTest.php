<?php

use App\Models\Caja;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;

// Ingreso rápido: vender mercadería que llega en el momento y todavía no está cargada.

test('un vendedor con permiso crea un producto nuevo y registra su ingreso', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $response = $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Pala Ancha Recién Llegada',
        'precio_costo' => 3000,
        'precio_venta' => 4500,
        'stock_minimo' => 2,
        'cantidad' => 5,
    ]);

    $response->assertOk()
        ->assertJsonPath('producto.nombre', 'Pala Ancha Recién Llegada')
        ->assertJsonPath('producto.stock', 5);

    $producto = Producto::where('nombre', 'Pala Ancha Recién Llegada')->first();

    expect($producto)->not->toBeNull()
        ->and($producto->stock)->toBe(5)
        ->and((float) $producto->precio_venta)->toBe(4500.0)
        ->and($producto->activo)->toBeTrue();

    $movimiento = MovimientoStock::where('producto_id', $producto->id)->first();

    expect($movimiento)->not->toBeNull()
        ->and($movimiento->tipo)->toBe(MovimientoStock::TIPO_INGRESO)
        ->and($movimiento->cantidad)->toBe(5)
        ->and($movimiento->stock_anterior)->toBe(0)
        ->and($movimiento->stock_posterior)->toBe(5)
        ->and($movimiento->user_id)->toBe($vendedor->id)
        ->and($movimiento->observacion)->toBe('Ingreso en venta — mercadería que llegó');
});

test('un vendedor con permiso ingresa mercadería de un producto existente sin stock', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'nombre' => 'Serrucho Sin Stock',
        'stock' => 0,
        'activo' => true,
    ]);

    $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'existente',
        'producto_id' => $producto->id,
        'cantidad' => 4,
        'observacion' => 'Llegó en el camión de la mañana',
    ])->assertOk();

    $producto->refresh();

    expect($producto->stock)->toBe(4);

    $movimiento = MovimientoStock::where('producto_id', $producto->id)->first();

    expect($movimiento->stock_anterior)->toBe(0)
        ->and($movimiento->stock_posterior)->toBe(4)
        ->and($movimiento->observacion)->toBe('Llegó en el camión de la mañana');
});

test('después del ingreso rápido la venta descuenta el stock correctamente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Machete Recién Llegado',
        'precio_costo' => 2000,
        'precio_venta' => 3500,
        'stock_minimo' => 1,
        'cantidad' => 3,
    ])->assertOk();

    $producto = Producto::where('nombre', 'Machete Recién Llegado')->first();

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 1],
        ],
    ])->assertRedirect();

    $producto->refresh();

    expect($producto->stock)->toBe(2)
        ->and(MovimientoStock::where('producto_id', $producto->id)->count())->toBe(2);
});

test('un usuario sin el permiso no puede registrar el ingreso rápido', function () {
    $usuario = User::factory()->create();

    Caja::factory()->create(['user_id' => $usuario->id, 'estado' => 'abierta']);

    $this->actingAs($usuario)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Producto Prohibido',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock_minimo' => 0,
        'cantidad' => 1,
    ])->assertForbidden();

    expect(Producto::where('nombre', 'Producto Prohibido')->exists())->toBeFalse()
        ->and(MovimientoStock::count())->toBe(0);
});

test('no se puede registrar el ingreso rápido sin una caja abierta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $response = $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Producto Sin Caja',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock_minimo' => 0,
        'cantidad' => 1,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('caja');

    expect(Producto::where('nombre', 'Producto Sin Caja')->exists())->toBeFalse();
});

test('el ingreso rápido valida la cantidad y los datos del producto nuevo', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    // Cantidad inválida
    $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Producto Con Cantidad Cero',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock_minimo' => 0,
        'cantidad' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('cantidad');

    // Falta el nombre del producto nuevo
    $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock_minimo' => 0,
        'cantidad' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('nombre');

    expect(Producto::count())->toBe(0)
        ->and(MovimientoStock::count())->toBe(0);
});

test('el historial de movimientos muestra la acción de ingreso en venta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $this->actingAs($vendedor)->postJson(route('ventas.ingreso-rapido'), [
        'modo' => 'nuevo',
        'nombre' => 'Producto Del Camión',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock_minimo' => 0,
        'cantidad' => 2,
    ])->assertOk();

    $producto = Producto::where('nombre', 'Producto Del Camión')->first();

    $response = $this->actingAs($vendedor)->get(route('movimientos-stock.producto', $producto));

    $response->assertStatus(200)
        ->assertSee('Producto Del Camión')
        ->assertSee('Ingreso en venta');
});
