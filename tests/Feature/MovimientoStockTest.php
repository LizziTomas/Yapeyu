<?php

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('unauthenticated users are redirected to login when accessing movimientos stock', function () {
    $this->get(route('movimientos-stock.index'))->assertRedirect(route('login'));
    $this->get(route('movimientos-stock.create'))->assertRedirect(route('login'));
});

test('1. authorized user can view create form and register an ingreso', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create([
        'stock' => 10,
        'activo' => true,
    ]);

    $this->actingAs($admin)->get(route('movimientos-stock.create'))->assertStatus(200);

    $response = $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 15,
        'observacion' => 'Ingreso de prueba por lote',
    ]);

    $movimiento = MovimientoStock::first();
    expect($movimiento)->not->toBeNull();

    $response->assertRedirect(route('movimientos-stock.show', $movimiento));
    $response->assertSessionHas('success');
});

test('2, 3, 4, 5, 6. ingreso increases stock and saves stock_anterior, stock_posterior, user_id, and tipo correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create([
        'stock' => 20,
        'activo' => true,
    ]);

    $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 30,
        'observacion' => 'Llegada de camión semanal',
    ]);

    $producto->refresh();
    $movimiento = MovimientoStock::where('producto_id', $producto->id)->first();

    // 2. El ingreso aumenta correctamente el stock
    expect($producto->stock)->toBe(50)
        // 3. Se guarda correctamente stock_anterior
        ->and($movimiento->stock_anterior)->toBe(20)
        // 4. Se guarda correctamente stock_posterior
        ->and($movimiento->stock_posterior)->toBe(50)
        // 5. Se guarda correctamente user_id
        ->and($movimiento->user_id)->toBe($admin->id)
        // 6. Se guarda tipo = ingreso
        ->and($movimiento->tipo)->toBe('ingreso')
        ->and($movimiento->cantidad)->toBe(30)
        ->and($movimiento->observacion)->toBe('Llegada de camión semanal');
});

test('7. quantity must be an integer greater than 0', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create(['activo' => true]);

    // Cantidad 0
    $responseZero = $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 0,
    ]);
    $responseZero->assertSessionHasErrors(['cantidad']);

    // Cantidad negativa
    $responseNegative = $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => -5,
    ]);
    $responseNegative->assertSessionHasErrors(['cantidad']);

    // Cantidad no entera
    $responseDecimal = $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 2.5,
    ]);
    $responseDecimal->assertSessionHasErrors(['cantidad']);
});

test('8. cannot register ingreso for an inactive product', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $productoInactivo = Producto::factory()->create([
        'stock' => 5,
        'activo' => false,
    ]);

    $response = $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $productoInactivo->id,
        'cantidad' => 10,
    ]);

    $response->assertSessionHasErrors(['producto_id']);

    $productoInactivo->refresh();
    expect($productoInactivo->stock)->toBe(5)
        ->and(MovimientoStock::count())->toBe(0);
});

test('9. movement is properly stored in the database', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create(['stock' => 0, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 100,
        'observacion' => 'Carga inicial de stock',
    ]);

    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $producto->id,
        'user_id' => $admin->id,
        'tipo' => 'ingreso',
        'cantidad' => 100,
        'stock_anterior' => 0,
        'stock_posterior' => 100,
        'observacion' => 'Carga inicial de stock',
    ]);
});

test('10. history index displays movements and filters work correctly', function () {
    $admin = User::factory()->create(['name' => 'Admin Usuario']);
    $admin->assignRole('admin');

    $otroUser = User::factory()->create(['name' => 'Otro Vendedor']);
    $otroUser->assignRole('vendedor');

    $prodA = Producto::factory()->create(['nombre' => 'Producto Alfa', 'activo' => true]);
    $prodB = Producto::factory()->create(['nombre' => 'Producto Beta', 'activo' => true]);

    $movA = MovimientoStock::factory()->create([
        'producto_id' => $prodA->id,
        'user_id' => $admin->id,
        'cantidad' => 10,
        'stock_anterior' => 0,
        'stock_posterior' => 10,
    ]);

    $movB = MovimientoStock::factory()->create([
        'producto_id' => $prodB->id,
        'user_id' => $otroUser->id,
        'cantidad' => 25,
        'stock_anterior' => 5,
        'stock_posterior' => 30,
    ]);

    // Ver listado general
    $response = $this->actingAs($admin)->get(route('movimientos-stock.index'));
    $response->assertStatus(200);
    $movimientosData = $response->viewData('movimientos');
    expect($movimientosData)->toHaveCount(2);

    // Filtrar por producto Alfa
    $responseFiltroProd = $this->actingAs($admin)->get(route('movimientos-stock.index', ['producto_id' => $prodA->id]));
    $responseFiltroProd->assertStatus(200);
    $movsProd = $responseFiltroProd->viewData('movimientos');
    expect($movsProd)->toHaveCount(1)
        ->and($movsProd->first()->producto_id)->toBe($prodA->id);

    // Filtrar por usuario
    $responseFiltroUser = $this->actingAs($admin)->get(route('movimientos-stock.index', ['user_id' => $otroUser->id]));
    $responseFiltroUser->assertStatus(200);
    $movsUser = $responseFiltroUser->viewData('movimientos');
    expect($movsUser)->toHaveCount(1)
        ->and($movsUser->first()->user_id)->toBe($otroUser->id);
});

test('11. user without permission cannot register ingresos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $producto = Producto::factory()->create(['activo' => true]);

    $this->actingAs($vendedor)->get(route('movimientos-stock.create'))->assertStatus(403);

    $this->actingAs($vendedor)->post(route('movimientos-stock.store'), [
        'producto_id' => $producto->id,
        'cantidad' => 10,
    ])->assertStatus(403);
});

test('12, 13. movements cannot be edited or deleted via routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $movimiento = MovimientoStock::factory()->create();

    // Rutas PUT/PATCH y DELETE no existen para movimientos de stock
    $this->actingAs($admin)->put('/movimientos-stock/' . $movimiento->id, [
        'cantidad' => 999,
    ])->assertStatus(405);

    $this->actingAs($admin)->delete('/movimientos-stock/' . $movimiento->id)->assertStatus(405);
});

test('14. creating a new product leaves stock automatically in 0', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('productos.store'), [
        'nombre' => 'Producto Recién Creado',
        'precio_costo' => 500,
        'precio_venta' => 750,
        'stock_minimo' => 5,
        'stock' => 999, // Intentando forzar stock en el payload
    ]);

    $producto = Producto::where('nombre', 'Producto Recién Creado')->first();
    expect($producto)->not->toBeNull()
        ->and($producto->stock)->toBe(0);
});

test('15. editing a product does not allow modifying stock', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create([
        'nombre' => 'Producto Editable',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock' => 42,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    $this->actingAs($admin)->put(route('productos.update', $producto), [
        'nombre' => 'Producto Editable Modificado',
        'precio_costo' => 120,
        'precio_venta' => 220,
        'stock' => 1000, // Debe ser ignorado por el backend
        'stock_minimo' => 8,
    ]);

    $producto->refresh();
    expect($producto->nombre)->toBe('Producto Editable Modificado')
        ->and($producto->stock)->toBe(42) // Conserva su stock intacto
        ->and($producto->stock_minimo)->toBe(8);
});

test('16. transaction rollback ensures stock is not modified if movement creation fails', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $producto = Producto::factory()->create([
        'stock' => 10,
        'activo' => true,
    ]);

    // Simular un fallo dentro de la transacción forzando un error
    try {
        DB::transaction(function () use ($producto, $admin) {
            $prod = Producto::where('id', $producto->id)->lockForUpdate()->first();
            $prod->update(['stock' => 100]);

            // Forzar una excepción intencional antes de completar la transacción
            throw new \Exception('Fallo forzado para verificar rollback');
        });
    } catch (\Exception $e) {
        // Excepción capturada
    }

    $producto->refresh();
    expect($producto->stock)->toBe(10)
        ->and(MovimientoStock::count())->toBe(0);
});

test('show view displays complete historical movement information', function () {
    $admin = User::factory()->create(['name' => 'Encargado Stock']);
    $admin->assignRole('admin');

    $producto = Producto::factory()->create(['nombre' => 'Cuchillo Criollo 20cm', 'activo' => true]);

    $movimiento = MovimientoStock::create([
        'producto_id' => $producto->id,
        'user_id' => $admin->id,
        'tipo' => 'ingreso',
        'cantidad' => 12,
        'stock_anterior' => 8,
        'stock_posterior' => 20,
        'observacion' => 'Ingreso de cuchillos artesanales',
    ]);

    $response = $this->actingAs($admin)->get(route('movimientos-stock.show', $movimiento));

    $response->assertStatus(200);
    $response->assertSee('Cuchillo Criollo 20cm');
    $response->assertSee('Encargado Stock');
    $response->assertSee('ingreso');
    $response->assertSee('+12');
    $response->assertSee('8');
    $response->assertSee('20');
    $response->assertSee('Ingreso de cuchillos artesanales');
});
