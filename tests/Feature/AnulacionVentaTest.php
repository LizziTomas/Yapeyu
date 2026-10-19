<?php

use App\Models\Caja;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;

/**
 * Registra una venta real a través del endpoint y devuelve el modelo creado.
 */
function ventaAnulable($test, User $vendedor, Producto $producto, int $cantidad = 2, string $medioPago = 'efectivo'): Venta
{
    $test->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => $medioPago,
        'productos' => [
            ['id' => $producto->id, 'cantidad' => $cantidad],
        ],
    ])->assertSessionHasNoErrors();

    return Venta::latest('id')->firstOrFail();
}

test('usuarios no autenticados son redirigidos al login al intentar anular una venta', function () {
    $venta = Venta::factory()->create();

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Cobro duplicado'])
        ->assertRedirect(route('login'));
});

test('un vendedor puede anular su propia venta con la caja abierta y el stock vuelve al inventario', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    $producto = Producto::factory()->create([
        'precio_costo' => 100.00,
        'precio_venta' => 200.00,
        'stock' => 10,
        'activo' => true,
    ]);

    $venta = ventaAnulable($this, $vendedor, $producto, 3);
    expect($producto->refresh()->stock)->toBe(7);

    $response = $this->actingAs($vendedor)->post(route('ventas.anular', $venta), [
        'motivo' => 'El cliente devolvió la mercadería',
    ]);

    $response->assertRedirect(route('ventas.show', $venta));
    $response->assertSessionHas('success');

    $venta->refresh();
    expect($venta->estado)->toBe('anulada')
        ->and($venta->motivo_anulacion)->toBe('El cliente devolvió la mercadería')
        ->and($venta->anulada_por)->toBe($vendedor->id)
        ->and($venta->anulada_at)->not->toBeNull();

    // El stock regresa al inventario
    expect($producto->refresh()->stock)->toBe(10);

    // Se registra el movimiento histórico de anulación
    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $producto->id,
        'user_id' => $vendedor->id,
        'tipo' => MovimientoStock::TIPO_ANULACION,
        'cantidad' => 3,
        'stock_anterior' => 7,
        'stock_posterior' => 10,
    ]);
});

test('una venta anulada deja de sumar en los totales y el arqueo de la caja', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    $producto = Producto::factory()->create([
        'precio_costo' => 100.00,
        'precio_venta' => 500.00,
        'stock' => 10,
        'activo' => true,
    ]);

    // Venta en efectivo por $1000
    $venta = ventaAnulable($this, $vendedor, $producto, 2);
    expect($caja->refresh()->totalVentasEfectivo())->toBe(1000.0);

    $this->actingAs($vendedor)->post(route('ventas.anular', $venta), [
        'motivo' => 'Venta mal cargada',
    ]);

    $caja->refresh();
    expect($caja->totalVentasEfectivo())->toBe(0.0)
        ->and($caja->totalVendido())->toBe(0.0)
        ->and($caja->dineroEsperado())->toBe(1000.0);
});

test('no se puede anular dos veces la misma venta y el stock no se duplica', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create(['precio_venta' => 200, 'precio_costo' => 100, 'stock' => 10, 'activo' => true]);
    $venta = ventaAnulable($this, $vendedor, $producto, 3);

    $this->actingAs($vendedor)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Primera anulación válida'])
        ->assertSessionHas('success');

    // La segunda intentona es rechazada por la policy
    $this->actingAs($vendedor)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Segunda anulación inválida'])
        ->assertStatus(403);

    expect($producto->refresh()->stock)->toBe(10)
        ->and(MovimientoStock::where('tipo', MovimientoStock::TIPO_ANULACION)->count())->toBe(1);
});

test('el motivo de anulación es obligatorio', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create(['precio_venta' => 200, 'precio_costo' => 100, 'stock' => 10, 'activo' => true]);
    $venta = ventaAnulable($this, $vendedor, $producto, 3);

    $this->actingAs($vendedor)
        ->post(route('ventas.anular', $venta), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    expect($venta->refresh()->estado)->toBe('completada')
        ->and($producto->refresh()->stock)->toBe(7)
        ->and(MovimientoStock::where('tipo', MovimientoStock::TIPO_ANULACION)->count())->toBe(0);
});

test('un vendedor no puede anular una venta que no le pertenece', function () {
    $vendedor1 = User::factory()->create();
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create();
    $vendedor2->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor1->id, 'estado' => 'abierta']);
    $caja2 = Caja::factory()->create(['user_id' => $vendedor2->id, 'estado' => 'abierta']);

    $ventaAjena = Venta::factory()->create([
        'user_id' => $vendedor2->id,
        'caja_id' => $caja2->id,
        'estado' => 'completada',
    ]);

    $this->actingAs($vendedor1)
        ->post(route('ventas.anular', $ventaAjena), ['motivo' => 'Intento de anulación ajena'])
        ->assertStatus(403);

    expect($ventaAjena->refresh()->estado)->toBe('completada');
});

test('un usuario sin el permiso anular ventas recibe 403', function () {
    $usuario = User::factory()->create();

    $venta = Venta::factory()->create();

    $this->actingAs($usuario)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Motivo de prueba'])
        ->assertStatus(403);

    expect($venta->refresh()->estado)->toBe('completada');
});

test('un vendedor no puede anular su venta si la caja ya fue cerrada', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaCerrada = Caja::factory()->cerrada()->create([
        'user_id' => $vendedor->id,
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1500.00,
        'monto_real' => 1500.00,
    ]);

    $venta = Venta::factory()->create([
        'user_id' => $vendedor->id,
        'caja_id' => $cajaCerrada->id,
        'estado' => 'completada',
    ]);

    $this->actingAs($vendedor)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Intento con caja cerrada'])
        ->assertStatus(403);

    expect($venta->refresh()->estado)->toBe('completada');
});

test('el admin puede anular una venta con la caja cerrada y el arqueo se recalcula', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    // Caja cerrada con una venta en efectivo de $500 ya contada en el arqueo
    $caja = Caja::factory()->cerrada(1500.00)->create([
        'user_id' => $vendedor->id,
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1500.00,
    ]);

    $producto = Producto::factory()->create([
        'precio_costo' => 300.00,
        'precio_venta' => 500.00,
        'stock' => 9,
        'activo' => true,
    ]);

    $venta = Venta::factory()->create([
        'user_id' => $vendedor->id,
        'caja_id' => $caja->id,
        'total' => 500.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
    ]);

    $venta->detalles()->create([
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 500.00,
        'costo_unitario' => 300.00,
        'subtotal' => 500.00,
    ]);

    $this->actingAs($admin)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Venta duplicada del cierre'])
        ->assertSessionHas('success');

    $caja->refresh();
    expect((float) $caja->monto_esperado)->toBe(1000.0)  // se recalcula sin la venta anulada
        ->and((float) $caja->monto_real)->toBe(1500.0)   // lo contado físicamente no se toca
        ->and((float) $caja->diferencia)->toBe(500.0);   // queda un sobrante por la venta anulada

    expect($producto->refresh()->stock)->toBe(10);
});

test('la venta anulada se excluye del reporte y se informa aparte', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    $producto = Producto::factory()->create(['precio_costo' => 100, 'precio_venta' => 250, 'stock' => 10, 'activo' => true]);
    $venta = ventaAnulable($this, $vendedor, $producto, 2, 'efectivo');

    $this->actingAs($admin)
        ->post(route('ventas.anular', $venta), ['motivo' => 'Anulación para el reporte'])
        ->assertSessionHas('success');

    $response = $this->actingAs($admin)->get(route('reportes.ventas'));
    $response->assertStatus(200);
    $response->assertSee('venta anulada');
});
