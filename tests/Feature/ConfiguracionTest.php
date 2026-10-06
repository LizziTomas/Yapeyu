<?php

use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\User;

test('admin can update the local name and it is shown in the layout', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('configuracion.edit'))->assertStatus(200);

    $this->actingAs($admin)
        ->patch(route('configuracion.update'), ['nombre_local' => 'Repuestos Yapeyú', 'usa_centavos' => '0'])
        ->assertRedirect(route('configuracion.edit'));

    expect(Configuracion::actual()->nombre_local)->toBe('Repuestos Yapeyú')
        ->and(Configuracion::actual()->usa_centavos)->toBeFalse();

    $this->actingAs($admin)->get(route('dashboard'))->assertSee('Repuestos Yapeyú');
});

test('vendedor cannot access the local configuration', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $this->actingAs($vendedor)->get(route('configuracion.edit'))->assertStatus(403);
    $this->actingAs($vendedor)
        ->patch(route('configuracion.update'), ['nombre_local' => 'Otro', 'usa_centavos' => '1'])
        ->assertStatus(403);
});

test('money values are shown with or without cents according to the configuration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Producto::factory()->create(['precio_venta' => 1234.50, 'activo' => true]);

    $this->actingAs($admin)->get(route('productos.index'))->assertSee('$ 1.234,50');

    Configuracion::actual()->update(['usa_centavos' => false]);

    $this->actingAs($admin)->get(route('productos.index'))
        ->assertSee('$ 1.235')
        ->assertDontSee('$ 1.234,50');
});

test('product prices with decimals are rejected when the local does not use cents', function () {
    Configuracion::actual()->update(['usa_centavos' => false]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $datos = ['nombre' => 'Aceite 4T 1L', 'precio_costo' => 1000, 'precio_venta' => 1500.50, 'stock_minimo' => 2];

    $this->actingAs($admin)->post(route('productos.store'), $datos)->assertSessionHasErrors('precio_venta');

    $datos['precio_venta'] = 1500;
    $this->actingAs($admin)->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
});
