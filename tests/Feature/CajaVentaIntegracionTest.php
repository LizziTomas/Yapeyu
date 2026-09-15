<?php

use App\Models\Caja;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;

test('una caja sin ventas conserva su monto inicial como dinero esperado y total vendido en cero', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 15000.00,
        'monto_esperado' => 15000.00,
    ]);

    expect($caja->totalVentasEfectivo())->toBe(0.0)
        ->and($caja->totalVentasTransferencia())->toBe(0.0)
        ->and($caja->totalVentasTarjeta())->toBe(0.0)
        ->and($caja->totalVendido())->toBe(0.0)
        ->and($caja->dineroEsperado())->toBe(15000.00);
});

test('una venta en efectivo aumenta el dinero esperado de la caja', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 10000.00,
    ]);

    $producto = Producto::factory()->create(['stock' => 10, 'precio_costo' => 1000, 'precio_venta' => 2500, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 2], // $5.000
        ],
    ]);

    $caja->refresh();
    expect($caja->totalVentasEfectivo())->toBe(5000.00)
        ->and($caja->totalVentasTransferencia())->toBe(0.0)
        ->and($caja->totalVentasTarjeta())->toBe(0.0)
        ->and($caja->totalVendido())->toBe(5000.00)
        ->and($caja->dineroEsperado())->toBe(15000.00); // $10.000 + $5.000
});

test('una venta por transferencia aumenta el total vendido pero NO el dinero esperado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 20000.00,
    ]);

    $producto = Producto::factory()->create(['stock' => 10, 'precio_costo' => 2000, 'precio_venta' => 4000, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'transferencia',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 1], // $4.000
        ],
    ]);

    $caja->refresh();
    expect($caja->totalVentasEfectivo())->toBe(0.0)
        ->and($caja->totalVentasTransferencia())->toBe(4000.00)
        ->and($caja->totalVentasTarjeta())->toBe(0.0)
        ->and($caja->totalVendido())->toBe(4000.00)
        ->and($caja->dineroEsperado())->toBe(20000.00); // No aumenta efectivo
});

test('una venta con tarjeta aumenta el total vendido pero NO el dinero esperado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 18000.00,
    ]);

    $producto = Producto::factory()->create(['stock' => 10, 'precio_costo' => 3000, 'precio_venta' => 6000, 'activo' => true]);

    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'tarjeta',
        'productos' => [
            ['id' => $producto->id, 'cantidad' => 1], // $6.000
        ],
    ]);

    $caja->refresh();
    expect($caja->totalVentasEfectivo())->toBe(0.0)
        ->and($caja->totalVentasTransferencia())->toBe(0.0)
        ->and($caja->totalVentasTarjeta())->toBe(6000.00)
        ->and($caja->totalVendido())->toBe(6000.00)
        ->and($caja->dineroEsperado())->toBe(18000.00); // No aumenta efectivo
});

test('multiples ventas de diferentes medios de pago calculan correctamente los subtotales y el dinero esperado segun el ejemplo oficial', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    // Monto inicial = $25.000
    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 25000.00,
    ]);

    $prod1 = Producto::factory()->create(['stock' => 100, 'precio_costo' => 1000, 'precio_venta' => 20000, 'activo' => true]);
    $prod2 = Producto::factory()->create(['stock' => 100, 'precio_costo' => 1000, 'precio_venta' => 15000, 'activo' => true]);
    $prod3 = Producto::factory()->create(['stock' => 100, 'precio_costo' => 1000, 'precio_venta' => 10000, 'activo' => true]);

    // Venta 1: $20.000 efectivo
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [['id' => $prod1->id, 'cantidad' => 1]],
    ]);

    // Venta 2: $15.000 transferencia
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'transferencia',
        'productos' => [['id' => $prod2->id, 'cantidad' => 1]],
    ]);

    // Venta 3: $10.000 tarjeta
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'tarjeta',
        'productos' => [['id' => $prod3->id, 'cantidad' => 1]],
    ]);

    // Venta 4: $10.000 efectivo
    $this->actingAs($vendedor)->post(route('ventas.store'), [
        'medio_pago' => 'efectivo',
        'productos' => [['id' => $prod3->id, 'cantidad' => 1]],
    ]);

    $caja->refresh();

    // Verificaciones exactas del ejemplo:
    // Ventas en efectivo = $30.000
    // Ventas por transferencia = $15.000
    // Ventas con tarjeta = $10.000
    // Total vendido = $55.000
    // Dinero esperado = $25.000 + $30.000 = $55.000
    expect($caja->totalVentasEfectivo())->toBe(30000.00)
        ->and($caja->totalVentasTransferencia())->toBe(15000.00)
        ->and($caja->totalVentasTarjeta())->toBe(10000.00)
        ->and($caja->totalVendido())->toBe(55000.00)
        ->and($caja->dineroEsperado())->toBe(55000.00);
});

test('los totales se calculan unicamente con ventas con estado completada', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
    ]);

    // Venta completada
    Venta::factory()->create([
        'caja_id' => $caja->id,
        'user_id' => $vendedor->id,
        'total' => 2000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
    ]);

    // Venta anulada (no debe sumarse)
    Venta::factory()->create([
        'caja_id' => $caja->id,
        'user_id' => $vendedor->id,
        'total' => 5000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'anulada',
    ]);

    expect($caja->totalVentasEfectivo())->toBe(2000.00)
        ->and($caja->totalVendido())->toBe(2000.00)
        ->and($caja->dineroEsperado())->toBe(3000.00); // $1000 + $2000
});

test('las ventas de otra caja no afectan los totales de la caja actual', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja1 = Caja::factory()->create(['user_id' => $vendedor->id, 'monto_inicial' => 5000.00]);
    $caja2 = Caja::factory()->create(['user_id' => $vendedor->id, 'monto_inicial' => 8000.00]);

    Venta::factory()->create(['caja_id' => $caja1->id, 'user_id' => $vendedor->id, 'total' => 3000.00, 'medio_pago' => 'efectivo', 'estado' => 'completada']);
    Venta::factory()->create(['caja_id' => $caja2->id, 'user_id' => $vendedor->id, 'total' => 9000.00, 'medio_pago' => 'efectivo', 'estado' => 'completada']);

    expect($caja1->totalVentasEfectivo())->toBe(3000.00)
        ->and($caja1->dineroEsperado())->toBe(8000.00)
        ->and($caja2->totalVentasEfectivo())->toBe(9000.00)
        ->and($caja2->dineroEsperado())->toBe(17000.00);
});

test('al cerrar la caja se congela el monto_esperado final con el dinero fisico esperado y calcula diferencia correctamente', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 10000.00,
    ]);

    // Ventas acumuladas: $5.000 efectivo, $3.000 transferencia -> Dinero esperado = $15.000
    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 5000.00, 'medio_pago' => 'efectivo', 'estado' => 'completada']);
    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 3000.00, 'medio_pago' => 'transferencia', 'estado' => 'completada']);

    // Arqueo físico: Vendedor cuenta $15.200 (sobrante de $200)
    $response = $this->actingAs($vendedor)->post(route('cajas.cerrar', $caja), [
        'monto_real' => 15200.00,
    ]);

    $response->assertRedirect(route('cajas.show', $caja));

    $caja->refresh();
    expect($caja->estaCerrada())->toBeTrue()
        ->and((float) $caja->monto_esperado)->toBe(15000.00)
        ->and((float) $caja->monto_real)->toBe(15200.00)
        ->and((float) $caja->diferencia)->toBe(200.00)
        ->and($caja->dineroEsperado())->toBe(15000.00);
});

test('el arqueo con faltante calcula correctamente la diferencia negativa en backend', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 20000.00,
    ]);

    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 10000.00, 'medio_pago' => 'efectivo', 'estado' => 'completada']);
    // Dinero esperado = $30.000

    // Vendedor cuenta $29.500 (faltante de $500)
    $this->actingAs($vendedor)->post(route('cajas.cerrar', $caja), [
        'monto_real' => 29500.00,
    ]);

    $caja->refresh();
    expect((float) $caja->monto_esperado)->toBe(30000.00)
        ->and((float) $caja->monto_real)->toBe(29500.00)
        ->and((float) $caja->diferencia)->toBe(-500.00);
});

test('la vista show de caja renderiza correctamente los subtotales de medios de pago total vendido y dinero esperado', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 25000.00,
    ]);

    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 30000.00, 'medio_pago' => 'efectivo', 'estado' => 'completada']);
    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 15000.00, 'medio_pago' => 'transferencia', 'estado' => 'completada']);
    Venta::factory()->create(['caja_id' => $caja->id, 'user_id' => $vendedor->id, 'total' => 10000.00, 'medio_pago' => 'tarjeta', 'estado' => 'completada']);

    $response = $this->actingAs($vendedor)->get(route('cajas.show', $caja));

    $response->assertStatus(200);
    $response->assertSee('25.000,00'); // Monto inicial
    $response->assertSee('30.000,00'); // Ventas efectivo
    $response->assertSee('15.000,00'); // Ventas transferencia
    $response->assertSee('10.000,00'); // Ventas tarjeta
    $response->assertSee('55.000,00'); // Total vendido y dinero esperado
    $response->assertSee('Dinero Físico Esperado en Caja');
});
