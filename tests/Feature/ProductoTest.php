<?php

use App\Models\Producto;
use App\Models\User;

test('unauthenticated users are redirected to login when accessing productos', function () {
    $response = $this->get(route('productos.index'));
    $response->assertRedirect(route('login'));
});

test('vendedor can view active products list and search', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);

    $productoActivo = Producto::factory()->create([
        'nombre' => 'Tornillo Phillips 1/2',
        'precio_costo' => 50.00,
        'precio_venta' => 80.00,
        'stock' => 100,
        'stock_minimo' => 20,
        'activo' => true,
    ]);

    $response = $this->actingAs($vendedor)->get(route('productos.index'));

    $response->assertStatus(200);
    $response->assertSee('Tornillo Phillips 1/2');
    $response->assertSee('$ 50,00');
    $response->assertSee('$ 80,00');
    $response->assertDontSee('Nuevo Producto');
    $response->assertDontSee('Desactivar');
});

test('vendedor cannot access create, edit, update, or destroy routes', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    $producto = Producto::factory()->create(['activo' => true]);

    $this->actingAs($vendedor)->get(route('productos.create'))->assertStatus(403);

    $this->actingAs($vendedor)->post(route('productos.store'), [
        'nombre' => 'Producto Ilegal',
        'precio_costo' => 10,
        'precio_venta' => 20,
        'stock' => 5,
        'stock_minimo' => 1,
    ])->assertStatus(403);

    $this->actingAs($vendedor)->get(route('productos.edit', $producto))->assertStatus(403);

    $this->actingAs($vendedor)->put(route('productos.update', $producto), [
        'nombre' => 'Producto Editado',
        'precio_costo' => 15,
        'precio_venta' => 25,
        'stock' => 10,
        'stock_minimo' => 2,
    ])->assertStatus(403);

    $this->actingAs($vendedor)->delete(route('productos.destroy', $producto))->assertStatus(403);
});

test('admin can view create form and store a new product', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('productos.create'))->assertStatus(200);

    $response = $this->actingAs($admin)->post(route('productos.store'), [
        'nombre' => 'Clavo Punta Paris 2 Pulgadas',
        'precio_costo' => 120.50,
        'precio_venta' => 190.00,
        'stock' => 50,
        'stock_minimo' => 10,
    ]);

    $response->assertRedirect(route('productos.index'));
    $response->assertSessionHas('success');

    $producto = Producto::where('nombre', 'Clavo Punta Paris 2 Pulgadas')->first();
    expect($producto)->not->toBeNull()
        ->and((float) $producto->precio_costo)->toBe(120.50)
        ->and((float) $producto->precio_venta)->toBe(190.00)
        ->and($producto->stock)->toBe(50)
        ->and($producto->stock_minimo)->toBe(10)
        ->and($producto->activo)->toBeTrue();
});

test('admin can edit and update a product', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $producto = Producto::factory()->create([
        'nombre' => 'Martillo Galponero',
        'precio_costo' => 2500.00,
        'precio_venta' => 3800.00,
        'stock' => 15,
        'stock_minimo' => 3,
        'activo' => true,
    ]);

    $this->actingAs($admin)->get(route('productos.edit', $producto))->assertStatus(200);

    $response = $this->actingAs($admin)->put(route('productos.update', $producto), [
        'nombre' => 'Martillo Galponero Reforzado',
        'precio_costo' => 2700.00,
        'precio_venta' => 4100.00,
        'stock' => 20,
        'stock_minimo' => 5,
    ]);

    $response->assertRedirect(route('productos.index'));
    $response->assertSessionHas('success');

    $producto->refresh();
    expect($producto->nombre)->toBe('Martillo Galponero Reforzado')
        ->and((float) $producto->precio_costo)->toBe(2700.00)
        ->and((float) $producto->precio_venta)->toBe(4100.00)
        ->and($producto->stock)->toBe(20)
        ->and($producto->stock_minimo)->toBe(5);
});

test('admin can deactivate product (soft deactivation without physical deletion)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $producto = Producto::factory()->create([
        'nombre' => 'Lija al Agua 240',
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->delete(route('productos.destroy', $producto));

    $response->assertRedirect(route('productos.index'));
    $response->assertSessionHas('success');

    // Comprobar que sigue existiendo en la base de datos pero con activo = false
    $this->assertDatabaseHas('productos', [
        'id' => $producto->id,
        'activo' => false,
    ]);

    $producto->refresh();
    expect($producto->activo)->toBeFalse();
});

test('inactive products are excluded from the main listing and search', function () {
    $user = User::factory()->create(['role' => 'vendedor']);

    Producto::factory()->create([
        'nombre' => 'Producto Activo Visible',
        'activo' => true,
    ]);

    Producto::factory()->create([
        'nombre' => 'Producto Inactivo Oculto',
        'activo' => false,
    ]);

    $response = $this->actingAs($user)->get(route('productos.index'));

    $response->assertStatus(200);
    $response->assertSee('Producto Activo Visible');
    $response->assertDontSee('Producto Inactivo Oculto');
});

test('inventory totals are computed correctly based only on active products', function () {
    $user = User::factory()->create(['role' => 'vendedor']);

    // Limpiar productos previos para prueba de totales exacta
    Producto::query()->delete();

    // Producto 1 activo: 10 unidades * $100 costo = $1000, * $150 venta = $1500
    Producto::factory()->create([
        'stock' => 10,
        'precio_costo' => 100,
        'precio_venta' => 150,
        'activo' => true,
    ]);

    // Producto 2 activo: 5 unidades * $200 costo = $1000, * $300 venta = $1500
    Producto::factory()->create([
        'stock' => 5,
        'precio_costo' => 200,
        'precio_venta' => 300,
        'activo' => true,
    ]);

    // Producto inactivo (no debe sumarse a los totales): 50 unidades * $1000 = $50000
    Producto::factory()->create([
        'stock' => 50,
        'precio_costo' => 1000,
        'precio_venta' => 2000,
        'activo' => false,
    ]);

    $response = $this->actingAs($user)->get(route('productos.index'));

    $response->assertStatus(200);
    // Totales esperados: 2 productos activos, 15 unidades, $2000 costo, $3000 venta
    $response->assertSee('$ 2.000,00');
    $response->assertSee('$ 3.000,00');
});

test('validation rejects negative stock and invalid prices', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(route('productos.store'), [
        'nombre' => '',
        'precio_costo' => -50,
        'precio_venta' => -100,
        'stock' => -10,
        'stock_minimo' => -2,
    ]);

    $response->assertSessionHasErrors([
        'nombre',
        'precio_costo',
        'precio_venta',
        'stock',
        'stock_minimo',
    ]);
});
