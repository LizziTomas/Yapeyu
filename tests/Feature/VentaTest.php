<?php

use App\Models\Caja;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Carbon\Carbon;

test('usuarios no autenticados son redirigidos al login al acceder a rutas de ventas', function () {
    $venta = Venta::factory()->create();

    $this->get(route('ventas.index'))->assertRedirect(route('login'));
    $this->get(route('ventas.create'))->assertRedirect(route('login'));
    $this->post(route('ventas.store'), [])->assertRedirect(route('login'));
    $this->get(route('ventas.show', $venta))->assertRedirect(route('login'));
});

test('un vendedor con caja abierta puede crear una venta exitosamente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    $producto = Producto::factory()->create([
        'nombre' => 'Destornillador Phillips',
        'precio_costo' => 500.00,
        'precio_venta' => 850.00,
        'stock' => 20,
        'activo' => true,
    ]);

    $response = $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            [
                'id' => $producto->id,
                'cantidad' => 3,
            ],
        ],
    ]);

    $venta = Venta::where('user_id', $vendedor->id)->first();
    expect($venta)->not->toBeNull()
        ->and($venta->caja_id)->toBe($caja->id)
        ->and($venta->user_id)->toBe($vendedor->id)
        ->and($venta->medio_pago)->toBe('efectivo')
        ->and((float) $venta->total)->toBe(2550.00)
        ->and($venta->estado)->toBe('completada');

    $response->assertRedirect(route('ventas.show', $venta));
    $response->assertSessionHas('success');

    $producto->refresh();
    expect($producto->stock)->toBe(17);
});

test('el formulario de nueva venta incluye el buscador de productos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    Producto::factory()->create([
        'nombre' => 'Taladro Percutor Buscable',
        'precio_venta' => 15000,
        'stock' => 5,
        'activo' => true,
    ]);

    $response = $this->actingAs($vendedor)->get(route('ventas.create'));

    $response->assertStatus(200);

    // Cada renglón tiene un buscador que despliega una lista filtrada mientras se escribe
    $response->assertSee('id="listaSugerencias"', false);
    $response->assertSee('function abrirLista(campo)', false);
    $response->assertSee('Taladro Percutor Buscable', false);

    // Cada renglón permite cargar un descuento
    $response->assertSee('productos[__I__][descuento]', false);
});

test('el descuento por renglon se aplica al subtotal y al total de la venta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $aceite = Producto::factory()->create(['precio_costo' => 4000, 'precio_venta' => 6800, 'stock' => 10, 'activo' => true]);
    $grasa = Producto::factory()->create(['precio_costo' => 3000, 'precio_venta' => 4900, 'stock' => 10, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $aceite->id, 'cantidad' => 2, 'descuento' => 10],
            ['id' => $grasa->id, 'cantidad' => 1, 'descuento' => 0],
        ],
    ])->assertSessionHasNoErrors();

    $venta = Venta::first();
    $detalleAceite = $venta->detalles->firstWhere('producto_id', $aceite->id);

    // 6800 x 2 = 13600, con 10% de descuento = 12240; más 4900 de la grasa = 17140
    expect($detalleAceite->descuento_porcentaje)->toBe(10)
        ->and((float) $detalleAceite->subtotal)->toBe(12240.00)
        ->and((float) $detalleAceite->precio_unitario)->toBe(6800.00)
        ->and((float) $venta->total)->toBe(17140.00);
});

test('un descuento fuera del rango 0 a 100 es rechazado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $producto = Producto::factory()->create(['stock' => 10, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [['id' => $producto->id, 'cantidad' => 1, 'descuento' => 150]],
    ])->assertSessionHasErrors('productos.0.descuento');

    expect(Venta::count())->toBe(0);
});

test('el mismo producto con distintos descuentos genera renglones separados y controla el stock sumado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $producto = Producto::factory()->create(['precio_venta' => 100, 'stock' => 5, 'activo' => true]);

    // 3 + 3 supera el stock de 5 aunque cada renglón por separado no lo supere
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 3, 'descuento' => 0],
            ['id' => $producto->id, 'cantidad' => 3, 'descuento' => 20],
        ],
    ])->assertSessionHasErrors('productos');

    expect(Venta::count())->toBe(0);

    // Dentro del stock: se guardan dos renglones, uno con descuento y otro sin
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 2, 'descuento' => 0],
            ['id' => $producto->id, 'cantidad' => 3, 'descuento' => 20],
        ],
    ])->assertSessionHasNoErrors();

    $venta = Venta::first();
    expect($venta->detalles)->toHaveCount(2)
        ->and((float) $venta->total)->toBe(440.00); // 200 + 300 con 20% = 240

    expect($producto->fresh()->stock)->toBe(0);
});

test('si la venta es rechazada por stock el formulario muestra el aviso', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $producto = Producto::factory()->create(['nombre' => 'Cadena 428', 'stock' => 2, 'activo' => true]);

    $this->actingAs($vendedor)
        ->from(route('ventas.create'))
        ->followingRedirects()
        ->post(route('ventas.store'), [
            'medio_pago' => 'efectivo',
            'productos' => [['id' => $producto->id, 'cantidad' => 5]],
        ])
        ->assertSee('No se pudo registrar la venta')
        ->assertSee("El producto 'Cadena 428' no tiene stock suficiente");
});

test('un vendedor sin caja abierta no puede crear una venta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $producto = Producto::factory()->create([
        'stock' => 10,
        'activo' => true,
    ]);

    // 1. Acceso al formulario de creación
    $this->actingAs($vendedor)
        ->get(route('ventas.create'))
        ->assertRedirect(route('cajas.index'))
        ->assertSessionHas('error');

    // 2. Envío por POST
    $response = $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            [
                'id' => $producto->id,
                'cantidad' => 2,
            ],
        ],
    ]);

    $response->assertRedirect(route('cajas.index'));
    $response->assertSessionHas('error');

    expect(Venta::count())->toBe(0);
    $producto->refresh();
    expect($producto->stock)->toBe(10);
});

test('la venta guarda correctamente cabecera y relaciones', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
    ]);

    $producto1 = Producto::factory()->create(['precio_costo' => 100, 'precio_venta' => 200, 'stock' => 10, 'activo' => true]);
    $producto2 = Producto::factory()->create(['precio_costo' => 300, 'precio_venta' => 500, 'stock' => 10, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'transferencia',
        'productos' => [
            ['id' => $producto1->id, 'cantidad' => 2], // 400
            ['id' => $producto2->id, 'cantidad' => 1], // 500
        ],
    ]);

    $venta = Venta::first();
    expect($venta)->not->toBeNull()
        ->and($venta->user->id)->toBe($vendedor->id)
        ->and($venta->caja->id)->toBe($caja->id)
        ->and($venta->medio_pago)->toBe('transferencia')
        ->and((float) $venta->total)->toBe(900.00)
        ->and($venta->detalles)->toHaveCount(2);
});

test('los detalles de la venta guardan producto, cantidad, precio historico, costo historico y subtotal', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'nombre' => 'Cinta Aisladora',
        'precio_costo' => 150.00,
        'precio_venta' => 250.00,
        'stock' => 50,
        'activo' => true,
    ]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 4],
        ],
    ]);

    $detalle = VentaDetalle::first();
    expect($detalle)->not->toBeNull()
        ->and($detalle->producto_id)->toBe($producto->id)
        ->and($detalle->cantidad)->toBe(4)
        ->and((float) $detalle->precio_unitario)->toBe(250.00)
        ->and((float) $detalle->costo_unitario)->toBe(150.00)
        ->and((float) $detalle->subtotal)->toBe(1000.00);
});

test('el stock se descuenta correctamente en multiples renglones', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $prod1 = Producto::factory()->create(['stock' => 10, 'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true]);
    $prod2 = Producto::factory()->create(['stock' => 25, 'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'tarjeta',
        'productos' => [
            ['id' => $prod1->id, 'cantidad' => 3],
            ['id' => $prod2->id, 'cantidad' => 10],
        ],
    ]);

    $prod1->refresh();
    $prod2->refresh();

    expect($prod1->stock)->toBe(7)
        ->and($prod2->stock)->toBe(15);
});

test('no se puede vender mas stock del disponible', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'nombre' => 'Taladro Percutor',
        'stock' => 5,
        'activo' => true,
    ]);

    $response = $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 6],
        ],
    ]);

    $response->assertSessionHasErrors('productos');

    expect(Venta::count())->toBe(0);
    $producto->refresh();
    expect($producto->stock)->toBe(5);
});

test('un producto inactivo no puede ser vendido', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $productoInactivo = Producto::factory()->create([
        'nombre' => 'Producto Desactivado',
        'stock' => 20,
        'activo' => false,
    ]);

    $response = $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $productoInactivo->id, 'cantidad' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('productos');
    expect(Venta::count())->toBe(0);
    $productoInactivo->refresh();
    expect($productoInactivo->stock)->toBe(20);
});

test('valores manipulados de precio costo o total enviados desde el cliente son ignorados', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'precio_costo' => 500.00,
        'precio_venta' => 1000.00,
        'stock' => 10,
        'activo' => true,
    ]);

    // Cliente intenta alterar precio, costo, subtotal y total
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'total' => 1.00, // manipulado
        'user_id' => 999, // manipulado
        'caja_id' => 999, // manipulado
        'productos' => [
            [
                'id' => $producto->id,
                'cantidad' => 2,
                'precio_unitario' => 1.00, // manipulado
                'costo_unitario' => 0.50, // manipulado
                'subtotal' => 2.00, // manipulado
            ],
        ],
    ]);

    $venta = Venta::first();
    expect((float) $venta->total)->toBe(2000.00)
        ->and($venta->user_id)->toBe($vendedor->id);

    $detalle = $venta->detalles->first();
    expect((float) $detalle->precio_unitario)->toBe(1000.00)
        ->and((float) $detalle->costo_unitario)->toBe(500.00)
        ->and((float) $detalle->subtotal)->toBe(2000.00);
});

test('una venta utiliza el precio y costo vigentes al momento exacto de confirmarse', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'precio_costo' => 300.00,
        'precio_venta' => 600.00,
        'stock' => 10,
        'activo' => true,
    ]);

    // Actualizamos precio en BD antes de confirmar
    $producto->update([
        'precio_costo' => 400.00,
        'precio_venta' => 750.00,
    ]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 1],
        ],
    ]);

    $detalle = VentaDetalle::first();
    expect((float) $detalle->precio_unitario)->toBe(750.00)
        ->and((float) $detalle->costo_unitario)->toBe(400.00);
});

test('una venta historica conserva su precio y costo unitario aunque posteriormente cambie el producto', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'nombre' => 'Pintura Latex 4L',
        'precio_costo' => 4000.00,
        'precio_venta' => 7000.00,
        'stock' => 10,
        'activo' => true,
    ]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 2],
        ],
    ]);

    $venta = Venta::first();
    $detalle = $venta->detalles->first();
    expect((float) $detalle->precio_unitario)->toBe(7000.00)
        ->and((float) $detalle->costo_unitario)->toBe(4000.00)
        ->and((float) $detalle->subtotal)->toBe(14000.00)
        ->and((float) $venta->total)->toBe(14000.00);

    // Meses después, el producto duplica su precio
    $producto->update([
        'precio_costo' => 8000.00,
        'precio_venta' => 14000.00,
    ]);

    // La venta histórica debe conservar sus valores inalterados
    $detalle->refresh();
    $venta->refresh();
    expect((float) $detalle->precio_unitario)->toBe(7000.00)
        ->and((float) $detalle->costo_unitario)->toBe(4000.00)
        ->and((float) $detalle->subtotal)->toBe(14000.00)
        ->and((float) $venta->total)->toBe(14000.00);
});

test('el vendedor solamente puede ver sus propias ventas en el listado', function () {
    $vendedor1 = User::factory()->create(['name' => 'Vendedor 1']);
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create(['name' => 'Vendedor 2']);
    $vendedor2->assignRole('vendedor');

    $caja1 = Caja::factory()->create(['user_id' => $vendedor1->id, 'estado' => 'abierta']);
    $caja2 = Caja::factory()->create(['user_id' => $vendedor2->id, 'estado' => 'abierta']);

    $venta1 = Venta::factory()->create(['user_id' => $vendedor1->id, 'caja_id' => $caja1->id, 'total' => 1111.00]);
    $venta2 = Venta::factory()->create(['user_id' => $vendedor2->id, 'caja_id' => $caja2->id, 'total' => 2222.00]);

    $response = $this->actingAs($vendedor1)->get(route('ventas.index'));

    $response->assertStatus(200);
    $response->assertSee('1.111,00');
    $response->assertDontSee('2.222,00');
});

test('el admin puede ver todas las ventas en el listado y ver cualquier venta', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create(['name' => 'Vendedor Marcelo']);
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $venta = Venta::factory()->create(['user_id' => $vendedor->id, 'caja_id' => $caja->id, 'total' => 5432.00]);

    // Admin en index
    $responseIndex = $this->actingAs($admin)->get(route('ventas.index'));
    $responseIndex->assertStatus(200);
    $responseIndex->assertSee('Vendedor Marcelo');
    $responseIndex->assertSee('5.432,00');

    // Admin en show
    $responseShow = $this->actingAs($admin)->get(route('ventas.show', $venta));
    $responseShow->assertStatus(200);
    $responseShow->assertSee('Vendedor Marcelo');
    $responseShow->assertSee('5.432,00');
});

test('un vendedor no puede ver el detalle de una venta perteneciente a otro vendedor', function () {
    $vendedor1 = User::factory()->create();
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create();
    $vendedor2->assignRole('vendedor');

    $caja2 = Caja::factory()->create(['user_id' => $vendedor2->id, 'estado' => 'abierta']);
    $ventaVendedor2 = Venta::factory()->create(['user_id' => $vendedor2->id, 'caja_id' => $caja2->id]);

    $this->actingAs($vendedor1)
        ->get(route('ventas.show', $ventaVendedor2))
        ->assertStatus(403);
});

test('una venta guarda correctamente los medios de pago permitidos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $producto = Producto::factory()->create(['stock' => 50, 'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true]);

    foreach (['efectivo', 'transferencia', 'tarjeta'] as $medio) {
        $response = $this->actingAs($vendedor)->post(route('ventas.store'), [
            'medio_pago' => $medio,
            'productos' => [
                ['id' => $producto->id, 'cantidad' => 1],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ventas', [
            'medio_pago' => $medio,
            'user_id' => $vendedor->id,
        ]);
    }
});

test('una venta por transferencia o tarjeta no modifica todavia el monto esperado de caja', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    $producto = Producto::factory()->create(['stock' => 10, 'precio_costo' => 100, 'precio_venta' => 200, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'transferencia',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 2],
        ],
    ]);

    $caja->refresh();
    // En esta etapa monto_esperado se mantiene igual (integración en etapa posterior)
    expect((float) $caja->monto_esperado)->toBe(1000.00);
});

test('si ocurre un error durante la transaccion se ejecuta rollback y el stock no queda modificado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto1 = Producto::factory()->create(['stock' => 10, 'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true]);
    $producto2 = Producto::factory()->create(['stock' => 2, 'precio_costo' => 10, 'precio_venta' => 20, 'activo' => true]);

    // Producto 1 tiene stock suficiente, pero Producto 2 no tiene stock suficiente
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto1->id, 'cantidad' => 5],
            ['id' => $producto2->id, 'cantidad' => 10], // Excede stock
        ],
    ]);

    expect(Venta::count())->toBe(0)
        ->and(VentaDetalle::count())->toBe(0);

    $producto1->refresh();
    $producto2->refresh();

    // El stock del producto 1 no debió descontarse gracias al rollback
    expect($producto1->stock)->toBe(10)
        ->and($producto2->stock)->toBe(2);
});

test('productos duplicados en una misma solicitud son consolidados correctamente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create([
        'precio_costo' => 100.00,
        'precio_venta' => 200.00,
        'stock' => 20,
        'activo' => true,
    ]);

    // Mismo producto enviado 2 veces en el arreglo
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 2],
            ['id' => $producto->id, 'cantidad' => 3],
        ],
    ]);

    $venta = Venta::first();
    expect($venta->detalles)->toHaveCount(1);

    $detalle = $venta->detalles->first();
    expect($detalle->cantidad)->toBe(5)
        ->and((float) $detalle->subtotal)->toBe(1000.00);

    $producto->refresh();
    expect($producto->stock)->toBe(15);
});

test('la venta genera un movimiento de stock tipo venta por cada producto vendido', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto1 = Producto::factory()->create(['precio_costo' => 100, 'precio_venta' => 200, 'stock' => 10, 'activo' => true]);
    $producto2 = Producto::factory()->create(['precio_costo' => 300, 'precio_venta' => 500, 'stock' => 10, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto1->id, 'cantidad' => 2],
            ['id' => $producto2->id, 'cantidad' => 4],
        ],
    ]);

    $venta = Venta::first();

    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $producto1->id,
        'user_id' => $vendedor->id,
        'tipo' => MovimientoStock::TIPO_VENTA,
        'cantidad' => 2,
        'stock_anterior' => 10,
        'stock_posterior' => 8,
        'observacion' => "Venta #{$venta->id}",
    ]);

    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $producto2->id,
        'user_id' => $vendedor->id,
        'tipo' => MovimientoStock::TIPO_VENTA,
        'cantidad' => 4,
        'stock_anterior' => 10,
        'stock_posterior' => 6,
        'observacion' => "Venta #{$venta->id}",
    ]);

    expect(MovimientoStock::where('tipo', MovimientoStock::TIPO_VENTA)->count())->toBe(2);
});

test('una venta rechazada no deja movimientos de stock registrados', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create(['precio_venta' => 200, 'precio_costo' => 100, 'stock' => 2, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 10], // Excede el stock disponible
        ],
    ]);

    expect(MovimientoStock::count())->toBe(0)
        ->and($producto->refresh()->stock)->toBe(2);
});

test('el historial de stock reconcilia el stock final de cada producto', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create(['precio_costo' => 100, 'precio_venta' => 200, 'stock' => 20, 'activo' => true]);

    // Carga inicial simulando un ingreso de mercadería
    $producto->movimientosStock()->create([
        'user_id' => $admin->id,
        'tipo' => MovimientoStock::TIPO_INGRESO,
        'cantidad' => 20,
        'stock_anterior' => 0,
        'stock_posterior' => 20,
        'observacion' => 'Carga inicial',
    ]);

    // Se venden 3 unidades
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 3],
        ],
    ]);

    // El administrador descarta 2 unidades por rotura
    $this->actingAs($admin)->post(route('movimientos-stock.descartar.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'motivo' => 'Rotura del envase',
    ]);

    // Stock real: 20 (ingreso) - 3 (venta) - 2 (descarte) = 15
    expect($producto->refresh()->stock)->toBe(15);

    // La suma de los movimientos registrados reconcilia exactamente con el stock real
    $movimientos = MovimientoStock::where('producto_id', $producto->id)->orderBy('id')->get();

    expect($movimientos)->toHaveCount(3);

    $sumaMovimientos = $movimientos->sum(function ($movimiento) {
        return $movimiento->sumaStock() ? $movimiento->cantidad : -$movimiento->cantidad;
    });

    expect($sumaMovimientos)->toBe(15)
        ->and($movimientos->last()->stock_posterior)->toBe(15);
});

test('el listado de ventas filtra por rango de fechas en hora argentina', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);

    // Venta dentro del rango (05/10/2026 en hora argentina)
    Venta::factory()->create([
        'user_id' => $admin->id,
        'caja_id' => $caja->id,
        'total' => 11111.00,
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-05 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Venta fuera del rango (20/09/2026 en hora argentina)
    Venta::factory()->create([
        'user_id' => $admin->id,
        'caja_id' => $caja->id,
        'total' => 22222.00,
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-09-20 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('ventas.index', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertSee('11.111,00');
    $response->assertDontSee('22.222,00');
});

test('el listado de ventas filtra por monto mínimo y máximo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);

    Venta::factory()->create(['user_id' => $admin->id, 'caja_id' => $caja->id, 'total' => 500.00, 'estado' => 'completada']);
    Venta::factory()->create(['user_id' => $admin->id, 'caja_id' => $caja->id, 'total' => 5000.00, 'estado' => 'completada']);
    Venta::factory()->create(['user_id' => $admin->id, 'caja_id' => $caja->id, 'total' => 90000.00, 'estado' => 'completada']);

    $response = $this->actingAs($admin)->get(route('ventas.index', [
        'monto_min' => 1000,
        'monto_max' => 10000,
    ]));

    $response->assertStatus(200);
    $response->assertSee('5.000,00');
    $response->assertDontSee('500,00');
    $response->assertDontSee('90.000,00');
});

test('los filtros de ventas se conservan en la paginación', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);

    // Más de 15 ventas para forzar la paginación
    Venta::factory()->count(20)->create([
        'user_id' => $admin->id,
        'caja_id' => $caja->id,
        'total' => 3000.00,
        'estado' => 'completada',
    ]);

    $response = $this->actingAs($admin)->get(route('ventas.index', ['monto_min' => 1000]));

    $response->assertStatus(200);
    // El enlace de la página 2 arrastra el filtro aplicado
    $response->assertSee('monto_min=1000', false);
});

test('un vendedor solo puede filtrar y ver sus propias ventas con los filtros aplicados', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $otro = User::factory()->create();
    $otro->assignRole('vendedor');

    $cajaPropia = Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);
    $cajaAjena = Caja::factory()->create(['user_id' => $otro->id, 'estado' => 'abierta']);

    Venta::factory()->create(['user_id' => $vendedor->id, 'caja_id' => $cajaPropia->id, 'total' => 4321.00, 'estado' => 'completada']);
    Venta::factory()->create(['user_id' => $otro->id, 'caja_id' => $cajaAjena->id, 'total' => 8765.00, 'estado' => 'completada']);

    $response = $this->actingAs($vendedor)->get(route('ventas.index', ['monto_min' => 1000, 'monto_max' => 10000]));

    $response->assertStatus(200);
    $response->assertSee('4.321,00');
    $response->assertDontSee('8.765,00');
});
