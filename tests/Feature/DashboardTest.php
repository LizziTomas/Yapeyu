<?php

use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;

test('unauthenticated users are redirected to login when accessing dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('admin sees global sales totals for today and this month from all users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaAdmin = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);
    $cajaVendedor = Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    // Venta de hoy del admin ($ 5.000)
    Venta::create([
        'caja_id' => $cajaAdmin->id,
        'user_id' => $admin->id,
        'total' => 5000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    // Venta de hoy del vendedor ($ 3.000)
    Venta::create([
        'caja_id' => $cajaVendedor->id,
        'user_id' => $vendedor->id,
        'total' => 3000.00,
        'medio_pago' => 'tarjeta',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    // Total del día global: $ 8.000 (2 ventas)
    $response->assertSee('$ 8.000');
    $response->assertSee('2 ventas registradas');
});

test('el vendedor ve un dashboard simplificado con sus accesos rapidos y sin metricas generales', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaAdmin = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);
    $cajaVendedor = Caja::factory()->create(['user_id' => $vendedor->id, 'estado' => 'abierta']);

    // Venta del admin ($ 10.000) que NO debe filtrarse al panel del vendedor
    Venta::create([
        'caja_id' => $cajaAdmin->id,
        'user_id' => $admin->id,
        'total' => 10000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    // Venta del vendedor ($ 4.000)
    Venta::create([
        'caja_id' => $cajaVendedor->id,
        'user_id' => $vendedor->id,
        'total' => 4000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($vendedor)->get(route('dashboard'));

    $response->assertStatus(200);

    // Accesos rápidos a sus tareas diarias
    $response->assertSee('Nueva venta');
    $response->assertSee('Mis ventas');
    $response->assertSee('Mi caja');

    // Sin gráficos, KPIs, reportes ni datos de otros usuarios
    $response->assertDontSee('graficoVentasSemana', false);
    $response->assertDontSee('totalesSemana', false);
    $response->assertDontSee('Ventas de hoy');
    $response->assertDontSee('unidades');
    $response->assertDontSee('$ 10.000');
    $response->assertDontSee('$ 4.000');
    $response->assertDontSee('Reportes');
});

test('breakdown of payment methods for today is calculated accurately', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);

    // Efectivo: $ 12.000
    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 12000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    // Transferencia: $ 8.000
    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 8000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    // Tarjeta: $ 5.000
    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 5000.00,
        'medio_pago' => 'tarjeta',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('$ 12.000'); // Efectivo
    $response->assertSee('$ 8.000');  // Transferencia
    $response->assertSee('$ 5.000');  // Tarjeta
    $response->assertSee('$ 25.000'); // Total de hoy
});

test('inventory totals and cost valuation are displayed correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Producto::query()->delete();

    // Producto 1: 10 unidades @ costo $ 500 = $ 5.000
    Producto::factory()->create([
        'nombre' => 'Producto Uno',
        'stock' => 10,
        'precio_costo' => 500.00,
        'precio_venta' => 800.00,
        'stock_minimo' => 2,
        'activo' => true,
    ]);

    // Producto 2: 5 unidades @ costo $ 1.000 = $ 5.000
    Producto::factory()->create([
        'nombre' => 'Producto Dos',
        'stock' => 5,
        'precio_costo' => 1000.00,
        'precio_venta' => 1500.00,
        'stock_minimo' => 1,
        'activo' => true,
    ]);

    // Producto inactivo (no debe sumarse)
    Producto::factory()->create([
        'stock' => 50,
        'precio_costo' => 2000.00,
        'precio_venta' => 3000.00,
        'activo' => false,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('15');
    $response->assertSee('unidades');
    $response->assertSee('2 productos activos');
    $response->assertSee('Costo: $ 10.000');
});

test('low stock alert correctly counts and lists products with stock <= stock_minimo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Producto con stock bajo (stock 2 <= stock_minimo 5)
    Producto::factory()->create([
        'nombre' => 'Aceite 2T 1L',
        'stock' => 2,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    // Producto con stock suficiente (stock 20 > stock_minimo 5)
    Producto::factory()->create([
        'nombre' => 'Grasa Litio 500g',
        'stock' => 20,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Aceite 2T 1L');
    $response->assertSee('Atención requerida');
});

test('dashboard displays user open caja with initial amount, cash sales, and physically expected money', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::create([
        'user_id' => $admin->id,
        'fecha_apertura' => now(),
        'monto_inicial' => 15000.00,
        'monto_esperado' => 15000.00,
        'estado' => 'abierta',
    ]);

    // Venta en efectivo ($ 6.000) -> suma al dinero físico
    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 6000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
    ]);

    // Venta en transferencia ($ 9.000) -> NO suma al dinero físico
    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 9000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Abierta');
    $response->assertSee('$ 15.000'); // Monto inicial
    $response->assertSee('$ 6.000');  // Ventas efectivo
    $response->assertSee('$ 21.000'); // Dinero físico esperado = 15000 + 6000
});

test('el panel del vendedor no expone los montos de su caja, solo el acceso', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::create([
        'user_id' => $vendedor->id,
        'fecha_apertura' => now(),
        'monto_inicial' => 15000.00,
        'monto_esperado' => 21000.00,
        'estado' => 'abierta',
    ]);

    $response = $this->actingAs($vendedor)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Mi caja');
    $response->assertSee('Abierta');
    $response->assertDontSee('Dinero físico esperado');
    $response->assertDontSee('$ 15.000');
    $response->assertDontSee('$ 21.000');
});

test('dashboard handles user without open caja gracefully', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $response = $this->actingAs($vendedor)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Sin Caja Abierta');
    $response->assertSee('Abrir Caja');
});

test('dashboard formats money values without decimal places when the local does not use cents', function () {
    Configuracion::actual()->update(['usa_centavos' => false]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = Caja::factory()->create(['user_id' => $admin->id, 'estado' => 'abierta']);

    Venta::create([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 150000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    // Debe mostrar $ 150.000 y NO $ 150.000,00
    $response->assertSee('$ 150.000');
    $response->assertDontSee('$ 150.000,00');
});

test('el dashboard incluye los contenedores y datos de los gráficos', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);

    // Contenedores de los gráficos
    $response->assertSee('id="graficoVentasSemana"', false);
    $response->assertSee('id="graficoMediosHoy"', false);

    // Datos inyectados para Chart.js
    $response->assertSee('var etiquetasSemana =', false);
    $response->assertSee('var totalesSemana =', false);

    // La serie de la última semana siempre tiene 7 puntos
    preg_match('/var totalesSemana = \[([^\]]*)\];/', $response->getContent(), $coincidencia);
    expect(explode(',', $coincidencia[1] ?? ''))->toHaveCount(7);
});

test('el gráfico de la última semana solo suma ventas dentro de los últimos 7 días', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $zona = 'America/Argentina/Buenos_Aires';

    // Venta de hoy y venta de hace 3 días: ambas entran en la serie
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 500.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->subDays(3)->setTimezone('UTC'),
    ]);

    // Venta de hace 30 días: NO debe aparecer en la serie (aunque sí en el mes si correspondiera)
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 99999.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->subDays(30)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertStatus(200);

    preg_match('/var totalesSemana = \[([^\]]*)\];/', $response->getContent(), $m);
    $valores = array_map('floatval', explode(',', $m[1] ?? ''));
    $suma = round(array_sum($valores), 2);

    // Solo la venta de hoy (1000) y la de hace 3 días (500)
    expect($suma)->toBe(1500.0)
        ->and($valores)->toHaveCount(7)
        ->and(max($valores))->toBe(1000.0);
});

test('el panel del vendedor no incluye graficos ni datos de ventas de otros usuarios', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $otro = User::factory()->create();
    $otro->assignRole('vendedor');

    $cajaPropia = Caja::factory()->create(['user_id' => $vendedor->id]);
    $cajaAjena = Caja::factory()->create(['user_id' => $otro->id]);

    $zona = 'America/Argentina/Buenos_Aires';

    Venta::forceCreate([
        'caja_id' => $cajaPropia->id,
        'user_id' => $vendedor->id,
        'total' => 1000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $cajaAjena->id,
        'user_id' => $otro->id,
        'total' => 77777.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($vendedor)->get(route('dashboard'));
    $response->assertStatus(200);

    // El panel del vendedor no recibe series para gráficos
    $response->assertDontSee('var totalesSemana', false);
    $response->assertDontSee('var etiquetasSemana', false);

    // Y no expone totales propios ni ajenos
    $response->assertDontSee('77.777');
    $response->assertDontSee('$ 1.000');
});

test('el dashboard muestra la tendencia porcentual contra el día anterior', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $zona = 'America/Argentina/Buenos_Aires';

    // Ayer: 1000. Hoy: 1500 => +50%
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->subDay()->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1500.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('50,0% vs. ayer');
});

test('el dashboard muestra la caída cuando se vende menos que ayer', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $zona = 'America/Argentina/Buenos_Aires';

    // Ayer: 2000. Hoy: 1000 => -50%
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 2000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->subDay()->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('50,0% vs. ayer');
});

test('el dashboard avisa cuando no hay ventas de ayer para comparar', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $zona = 'America/Argentina/Buenos_Aires';

    // Solo hay venta de hoy: no existe base de comparación
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::now($zona)->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Sin base de ayer');
});
