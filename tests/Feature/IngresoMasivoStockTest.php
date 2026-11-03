<?php

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea un administrador para las pruebas de carga masiva.
 */
function adminCargaMasiva(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('un invitado es redirigido al login en la carga masiva de stock', function () {
    $this->get(route('movimientos-stock.ingreso-masivo'))->assertRedirect(route('login'));
    $this->post(route('movimientos-stock.ingreso-masivo.store'))->assertRedirect(route('login'));
});

test('un vendedor no puede entrar a la carga masiva de stock', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $this->actingAs($vendedor)->get(route('movimientos-stock.ingreso-masivo'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [1 => 5],
    ])->assertForbidden();
});

test('el administrador ve la tabla de productos activos con su stock actual', function () {
    $admin = adminCargaMasiva();

    Producto::factory()->create(['nombre' => 'Producto Activo', 'stock' => 12, 'activo' => true]);
    Producto::factory()->create(['nombre' => 'Producto Inactivo', 'activo' => false]);

    $respuesta = $this->actingAs($admin)->get(route('movimientos-stock.ingreso-masivo'));

    $respuesta->assertOk()
        ->assertSee('Producto Activo')
        ->assertSee('Ingreso masivo de stock')
        ->assertDontSee('Producto Inactivo')
        ->assertSee('cantidades[');
});

test('registra un ingreso por cada producto con cantidad mayor a cero', function () {
    $admin = adminCargaMasiva();

    $productoA = Producto::factory()->create(['nombre' => 'Producto A', 'stock' => 10, 'activo' => true]);
    $productoB = Producto::factory()->create(['nombre' => 'Producto B', 'stock' => 0, 'activo' => true]);
    $productoC = Producto::factory()->create(['nombre' => 'Producto C', 'stock' => 5, 'activo' => true]);

    $respuesta = $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $productoA->id => 5,
            $productoB->id => 8,
            $productoC->id => 0,
        ],
        'observacion' => 'Llegada de lote #402',
    ]);

    $respuesta->assertRedirect(route('movimientos-stock.index'))
        ->assertSessionHas('success');

    expect($productoA->refresh()->stock)->toBe(15)
        ->and($productoB->refresh()->stock)->toBe(8)
        ->and($productoC->refresh()->stock)->toBe(5);

    // Solo se generan movimientos para los productos con cantidad mayor a 0.
    expect(MovimientoStock::count())->toBe(2);

    $movimientoA = MovimientoStock::where('producto_id', $productoA->id)->first();

    expect($movimientoA->tipo)->toBe(MovimientoStock::TIPO_INGRESO)
        ->and($movimientoA->cantidad)->toBe(5)
        ->and($movimientoA->stock_anterior)->toBe(10)
        ->and($movimientoA->stock_posterior)->toBe(15)
        ->and($movimientoA->user_id)->toBe($admin->id)
        ->and($movimientoA->observacion)->toBe('Llegada de lote #402');

    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $productoB->id,
        'cantidad' => 8,
        'stock_anterior' => 0,
        'stock_posterior' => 8,
    ]);
});

test('ignora campos vacíos y usa una observación por defecto', function () {
    $admin = adminCargaMasiva();

    $producto = Producto::factory()->create(['stock' => 4, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $producto->id => 3,
        ],
        'observacion' => '',
    ])->assertRedirect(route('movimientos-stock.index'));

    $movimiento = MovimientoStock::first();

    expect($producto->refresh()->stock)->toBe(7)
        ->and($movimiento->observacion)->toBe('Ingreso masivo de mercadería');
});

test('exige al menos una cantidad mayor a cero', function () {
    $admin = adminCargaMasiva();

    $producto = Producto::factory()->create(['stock' => 4, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $producto->id => 0,
        ],
    ])->assertSessionHasErrors(['cantidades']);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $producto->id => '',
        ],
    ])->assertSessionHasErrors(['cantidades']);

    expect($producto->refresh()->stock)->toBe(4)
        ->and(MovimientoStock::count())->toBe(0);
});

test('rechaza cantidades negativas o no enteras', function () {
    $admin = adminCargaMasiva();

    $producto = Producto::factory()->create(['stock' => 4, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [$producto->id => -2],
    ])->assertSessionHasErrors(['cantidades.'.$producto->id]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [$producto->id => '2.5'],
    ])->assertSessionHasErrors(['cantidades.'.$producto->id]);

    expect($producto->refresh()->stock)->toBe(4)
        ->and(MovimientoStock::count())->toBe(0);
});

test('no registra nada si uno de los productos ya no está activo', function () {
    $admin = adminCargaMasiva();

    $activo = Producto::factory()->create(['stock' => 10, 'activo' => true]);
    $inactivo = Producto::factory()->create(['stock' => 10, 'activo' => false]);

    $respuesta = $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $activo->id => 5,
            $inactivo->id => 5,
        ],
    ]);

    $respuesta->assertSessionHasErrors(['cantidades']);

    expect($activo->refresh()->stock)->toBe(10)
        ->and($inactivo->refresh()->stock)->toBe(10)
        ->and(MovimientoStock::count())->toBe(0);
});

test('no registra nada si uno de los productos no existe', function () {
    $admin = adminCargaMasiva();

    $producto = Producto::factory()->create(['stock' => 10, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $producto->id => 5,
            999999 => 3,
        ],
    ])->assertSessionHasErrors(['cantidades']);

    expect($producto->refresh()->stock)->toBe(10)
        ->and(MovimientoStock::count())->toBe(0);
});

test('el stock de cada producto sigue coincidiendo con la suma de sus movimientos', function () {
    $admin = adminCargaMasiva();

    $productoA = Producto::factory()->create(['stock' => 0, 'activo' => true]);
    $productoB = Producto::factory()->create(['stock' => 0, 'activo' => true]);

    $this->actingAs($admin)->post(route('movimientos-stock.ingreso-masivo.store'), [
        'cantidades' => [
            $productoA->id => 7,
            $productoB->id => 11,
        ],
    ]);

    foreach ([$productoA, $productoB] as $producto) {
        $entradas = (int) DB::table('movimientos_stock')
            ->where('producto_id', $producto->id)
            ->whereIn('tipo', [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_ANULACION])
            ->sum('cantidad');

        $salidas = (int) DB::table('movimientos_stock')
            ->where('producto_id', $producto->id)
            ->whereIn('tipo', [MovimientoStock::TIPO_VENTA, MovimientoStock::TIPO_DESCARTE])
            ->sum('cantidad');

        expect($producto->refresh()->stock)->toBe($entradas - $salidas);
    }
});
