<?php

use App\Models\Caja;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;

test('1. unauthenticated users are redirected to login when accessing reportes ventas', function () {
    $this->get(route('reportes.ventas'))->assertRedirect(route('login'));
});

test('1b. solo el administrador puede acceder a los reportes de ventas', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $this->actingAs($admin)->get(route('reportes.ventas'))->assertStatus(200);

    // El vendedor tiene "ver ventas" pero NO "ver reportes"
    $this->actingAs($vendedor)->get(route('reportes.ventas'))->assertStatus(403);
    $this->actingAs($vendedor)->get(route('reportes.ventas.exportar'))->assertStatus(403);
});

test('2. report calculates correct sales count and total amount for a given period', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    // Ventas completadas dentro del período
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 12000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-05 14:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 18000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-06 11:30:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Venta completada fuera del período (mes anterior)
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 50000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-09-20 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertSee('$ ' . monto(30000));
    $response->assertSee('2 operaciones registradas');
    $response->assertDontSee('$ ' . monto(50000));
});

test('3. report calculates correct breakdown by payment methods', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $fecha = Carbon::parse('2026-10-05 12:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    // Efectivo: $ 15.000
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 15000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // Transferencia: $ 25.000
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 25000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // Tarjeta: $ 10.000
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 10000.00,
        'medio_pago' => 'tarjeta',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertSee('$ ' . monto(15000)); // Efectivo
    $response->assertSee('$ ' . monto(25000)); // Transferencia
    $response->assertSee('$ ' . monto(10000)); // Tarjeta
    $response->assertSee('$ ' . monto(50000)); // Total
});

test('4. uncompleted or annulled sales are strictly excluded from totals and table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $fecha = Carbon::parse('2026-10-05 12:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    // Venta completada
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 10000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // Venta anulada (no debe sumarse ni mostrarse)
    $ventaAnulada = Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 88888.00,
        'medio_pago' => 'efectivo',
        'estado' => 'anulada',
        'created_at' => $fecha,
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertSee('$ ' . monto(10000));
    $response->assertDontSee('$ ' . monto(88888));
    $response->assertDontSee('ventas/' . $ventaAnulada->id);
    expect($response->viewData('ventas')->pluck('id'))->not->toContain($ventaAnulada->id);
});

test('5. date range filter correctly includes and excludes boundary dates', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    // Venta día 04 (dentro)
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1100.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-04 15:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Venta día 10 (dentro)
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 2200.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-10 15:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Venta día 11 (fuera)
    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 3300.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-11 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-04',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertSee('$ ' . monto(3300)); // Total 1100 + 2200
    $response->assertSee('2 operaciones registradas');
});

test('6. admin can filter report by specific vendedor or view all', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedorA = User::factory()->create(['name' => 'Carlos Vendedor']);
    $vendedorA->assignRole('vendedor');

    $vendedorB = User::factory()->create(['name' => 'Laura Vendedora']);
    $vendedorB->assignRole('vendedor');

    $cajaA = Caja::factory()->create(['user_id' => $vendedorA->id]);
    $cajaB = Caja::factory()->create(['user_id' => $vendedorB->id]);

    $fecha = Carbon::parse('2026-10-05 12:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    Venta::forceCreate([
        'caja_id' => $cajaA->id,
        'user_id' => $vendedorA->id,
        'total' => 4000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    Venta::forceCreate([
        'caja_id' => $cajaB->id,
        'user_id' => $vendedorB->id,
        'total' => 7000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // Filtrar solo Vendedor A
    $responseA = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
        'user_id' => $vendedorA->id,
    ]));

    $responseA->assertStatus(200);
    $responseA->assertSee('$ ' . monto(4000));
    $responseA->assertSee('1 operación registrada');
    $responseA->assertDontSee('$ ' . monto(7000));

    // Ver todos (sin user_id)
    $responseAll = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $responseAll->assertStatus(200);
    $responseAll->assertSee('$ ' . monto(11000));
    $responseAll->assertSee('2 operaciones registradas');
});

test('7. el vendedor no puede acceder al reporte aunque fuerce parametros por URL', function () {
    $vendedor1 = User::factory()->create(['name' => 'Vendedor Uno']);
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create(['name' => 'Vendedor Dos']);
    $vendedor2->assignRole('vendedor');

    $caja1 = Caja::factory()->create(['user_id' => $vendedor1->id]);
    $caja2 = Caja::factory()->create(['user_id' => $vendedor2->id]);

    $fecha = Carbon::parse('2026-10-05 12:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    // Venta Vendedor 1: $ 5.000
    Venta::forceCreate([
        'caja_id' => $caja1->id,
        'user_id' => $vendedor1->id,
        'total' => 5000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // Venta Vendedor 2: $ 95.000
    Venta::forceCreate([
        'caja_id' => $caja2->id,
        'user_id' => $vendedor2->id,
        'total' => 95000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    // El vendedor no puede entrar al reporte aunque fuerce el parámetro user_id
    $response = $this->actingAs($vendedor1)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
        'user_id' => $vendedor2->id,
    ]));

    $response->assertStatus(403);
});

test('8. filter by payment method isolates sales for that method', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $fecha = Carbon::parse('2026-10-05 12:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 3000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 6000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
        'medio_pago' => 'transferencia',
    ]));

    $response->assertStatus(200);
    $response->assertSee('$ ' . monto(6000));
    $response->assertDontSee('$ ' . monto(3000));
    $response->assertSee('1 operación registrada');
});

test('9. invalid date ranges (fecha_hasta before fecha_desde) are rejected with validation errors', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-15',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertSessionHasErrors(['fecha_hasta']);
});

test('10. handles Argentine timezone day boundary correctly without shifting operations', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    // Venta a las 23:30 hs del 15 de octubre en Argentina (02:30 hs UTC del 16 de octubre)
    $ventaFinDia = Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 17500.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-15 23:30:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Venta a las 00:15 hs del 16 de octubre en Argentina (03:15 hs UTC del 16 de octubre)
    $ventaDiaSiguiente = Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 29000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-16 00:15:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    // Consultar hasta el 15 de octubre
    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-15',
        'fecha_hasta' => '2026-10-15',
    ]));

    $response->assertStatus(200);
    // Debe incluir la venta de las 23:30 hs
    $response->assertSee('$ ' . monto(17500));
    $response->assertSee('ventas/' . $ventaFinDia->id);
    // Y NO debe incluir la del día 16 a las 00:15 hs
    $response->assertDontSee('$ ' . monto(29000));
    $response->assertDontSee('ventas/' . $ventaDiaSiguiente->id);

    $ventasIds = $response->viewData('ventas')->pluck('id');
    expect($ventasIds)->toContain($ventaFinDia->id)
        ->and($ventasIds)->not->toContain($ventaDiaSiguiente->id);
});

test('11. consistency between period summary and detailed operations table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $fecha = Carbon::parse('2026-10-05 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 1250.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 3750.00,
        'medio_pago' => 'tarjeta',
        'estado' => 'completada',
        'created_at' => $fecha,
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $ventasPaginadas = $response->viewData('ventas');
    $cantidadVentas = $response->viewData('cantidadVentas');
    $totalVendido = $response->viewData('totalVendido');

    expect($cantidadVentas)->toBe(2)
        ->and($totalVendido)->toBe(5000.00)
        ->and($ventasPaginadas->total())->toBe(2)
        ->and((float) $ventasPaginadas->sum('total'))->toBe(5000.00);
});

test('12. pagination links preserve selected filter query parameters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    $fecha = Carbon::parse('2026-10-05 10:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC');

    // Crear 20 ventas para forzar paginación (15 por página)
    for ($i = 1; $i <= 20; $i++) {
        Venta::forceCreate([
            'caja_id' => $caja->id,
            'user_id' => $admin->id,
            'total' => 100.00,
            'medio_pago' => 'efectivo',
            'estado' => 'completada',
            'created_at' => $fecha,
        ]);
    }

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
        'medio_pago' => 'efectivo',
    ]));

    $response->assertStatus(200);
    // Verificar que los links de paginación contengan los parámetros de filtro
    $response->assertSee('fecha_desde=2026-10-01');
    $response->assertSee('fecha_hasta=2026-10-10');
    $response->assertSee('medio_pago=efectivo');
    $response->assertSee('page=2');
});

test('exporta el detalle de ventas del período en formato CSV para Excel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 12000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-05 14:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 18000.00,
        'medio_pago' => 'transferencia',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-06 11:30:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas.exportar', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('reporte_ventas_2026-10-01_a_2026-10-10.csv');

    $contenido = $response->streamedContent();

    expect($contenido)->toContain('"N° Venta";Fecha;Hora;Vendedor;Caja;"Medio de pago";Estado;Total')
        ->and($contenido)->toContain('Efectivo')
        ->and($contenido)->toContain('Transferencia')
        ->and($contenido)->toContain(monto(12000))
        ->and($contenido)->toContain(monto(30000));
});

test('la exportación CSV queda reservada al administrador', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($vendedor)->get(route('reportes.ventas.exportar', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]))->assertStatus(403);

    $this->actingAs($admin)->get(route('reportes.ventas.exportar', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]))->assertStatus(200);
});

test('displays empty state when no completed sales match the filters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-01-01',
        'fecha_hasta' => '2026-01-02',
    ]));

    $response->assertStatus(200);
    $response->assertSee('Sin operaciones encontradas');
    $response->assertSee('No se encontraron ventas completadas para el período y filtros seleccionados');
});

test('el reporte incluye los datos y los contenedores de los gráficos del período', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $caja = Caja::factory()->create(['user_id' => $admin->id]);

    Venta::forceCreate([
        'caja_id' => $caja->id,
        'user_id' => $admin->id,
        'total' => 12000.00,
        'medio_pago' => 'efectivo',
        'estado' => 'completada',
        'created_at' => Carbon::parse('2026-10-05 14:00:00', 'America/Argentina/Buenos_Aires')->setTimezone('UTC'),
    ]);

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2026-10-01',
        'fecha_hasta' => '2026-10-10',
    ]));

    $response->assertStatus(200);

    // Contenedores de los gráficos
    $response->assertSee('id="graficoEvolucion"', false);
    $response->assertSee('id="graficoMediosPago"', false);

    // Datos inyectados en la vista para Chart.js
    $response->assertSee('var etiquetasSerie =', false);
    $response->assertSee('var valoresSerie =', false);
    $response->assertSee('var agrupacion = "diaria"', false);

    // El período de 10 días genera 10 puntos en la serie (las barras se escapan como \/)
    $response->assertSee('var etiquetasSerie = ["01\/10","02\/10","03\/10","04\/10","05\/10","06\/10","07\/10","08\/10","09\/10","10\/10"];', false);

    // La venta del 05/10 cae en el quinto punto de la serie
    $response->assertSee('var valoresSerie = [0,0,0,0,12000,0,0,0,0,0];', false);
});

test('el gráfico del reporte se agrupa por mes cuando el período es extenso', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('reportes.ventas', [
        'fecha_desde' => '2025-01-01',
        'fecha_hasta' => '2026-12-31',
    ]));

    $response->assertStatus(200);
    $response->assertSee('var agrupacion = "mensual"', false);
    $response->assertSee('Por mes');
});
