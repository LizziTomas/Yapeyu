<?php

use App\Models\Configuracion;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Services\ImportacionProductos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Crea un administrador para las pruebas de importación.
 */
function adminImportacion(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

/**
 * Envía un contenido al análisis y devuelve la respuesta.
 */
function analizarImportacion($test, User $admin, string $contenido)
{
    return $test->actingAs($admin)->post(route('productos.importar.analizar'), [
        'texto' => $contenido,
    ]);
}

/**
 * Importa un contenido completo (analizar + confirmar) y devuelve la respuesta final.
 */
function importar($test, User $admin, string $contenido)
{
    analizarImportacion($test, $admin, $contenido)->assertRedirect(route('productos.importar.previsualizar'));

    $preview = $test->actingAs($admin)->get(route('productos.importar.previsualizar'));
    $preview->assertOk();

    return $test->actingAs($admin)->post(route('productos.importar.confirmar'), [
        'token' => $preview->viewData('token'),
    ]);
}

test('un invitado es redirigido al login en la importación de productos', function () {
    $this->get(route('productos.importar'))->assertRedirect(route('login'));
    $this->get(route('productos.importar.plantilla'))->assertRedirect(route('login'));
    $this->post(route('productos.importar.analizar'))->assertRedirect(route('login'));
    $this->get(route('productos.importar.previsualizar'))->assertRedirect(route('login'));
    $this->post(route('productos.importar.confirmar'))->assertRedirect(route('login'));
});

test('un vendedor no puede entrar a la importación de productos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $this->actingAs($vendedor)->get(route('productos.importar'))->assertForbidden();
    $this->actingAs($vendedor)->get(route('productos.importar.plantilla'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('productos.importar.analizar'), ['texto' => 'x'])->assertForbidden();
    $this->actingAs($vendedor)->get(route('productos.importar.previsualizar'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('productos.importar.confirmar'))->assertForbidden();
});

test('el administrador ve el formulario y descarga la plantilla con BOM y encabezados', function () {
    $admin = adminImportacion();

    $this->actingAs($admin)->get(route('productos.importar'))
        ->assertOk()
        ->assertSee('Importar productos')
        ->assertSee('pegar desde Excel');

    $respuesta = $this->actingAs($admin)->get(route('productos.importar.plantilla'));

    $respuesta->assertOk();
    expect($respuesta->headers->get('content-type'))->toContain('text/csv');

    $contenido = $respuesta->getContent();

    expect($contenido)->toStartWith("\xEF\xBB\xBF")
        ->and($contenido)->toContain('nombre;precio_costo;precio_venta;stock_minimo;stock_inicial');
});

test('importa un CSV con punto y coma, acentos en Windows-1252 y coma decimal', function () {
    $admin = adminImportacion();

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Bombacha de campo;1.200,50;1.800,00;5;10\n"
        ."Camisa de trabajo;8.500,00;12.900,00;3;0\n";

    // Excel en español guarda el CSV en Windows-1252.
    $contenido = mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');

    analizarImportacion($this, $admin, $contenido)->assertRedirect(route('productos.importar.previsualizar'));

    $preview = $this->actingAs($admin)->get(route('productos.importar.previsualizar'));

    $preview->assertOk()
        ->assertSee('Bombacha de campo')
        ->assertSee('Camisa de trabajo')
        ->assertSee('Nuevo')
        ->assertSee('$ 1.200,50');

    $this->actingAs($admin)->post(route('productos.importar.confirmar'), [
        'token' => $preview->viewData('token'),
    ])->assertRedirect(route('productos.index'))
        ->assertSessionHas('success');

    $bombacha = Producto::where('nombre', 'Bombacha de campo')->first();
    $camisa = Producto::where('nombre', 'Camisa de trabajo')->first();

    expect($bombacha)->not->toBeNull()
        ->and($camisa)->not->toBeNull()
        ->and((float) $bombacha->precio_costo)->toBe(1200.50)
        ->and((float) $bombacha->precio_venta)->toBe(1800.00)
        ->and($bombacha->stock)->toBe(10)
        ->and($bombacha->stock_minimo)->toBe(5)
        ->and($camisa->stock)->toBe(0);

    // El stock inicial se registra como movimiento de ingreso.
    $this->assertDatabaseHas('movimientos_stock', [
        'producto_id' => $bombacha->id,
        'user_id' => $admin->id,
        'tipo' => MovimientoStock::TIPO_INGRESO,
        'cantidad' => 10,
        'stock_anterior' => 0,
        'stock_posterior' => 10,
    ]);

    // El producto sin stock inicial no genera movimiento.
    expect(MovimientoStock::where('producto_id', $camisa->id)->count())->toBe(0);
});

test('importa un CSV separado por comas y con punto decimal', function () {
    $admin = adminImportacion();

    $csv = "nombre,precio_costo,precio_venta,stock_minimo,stock_inicial\n"
        ."Taladro Percutor,1234.5,1999.99,2,4\n";

    importar($this, $admin, $csv);

    $producto = Producto::where('nombre', 'Taladro Percutor')->first();

    expect($producto)->not->toBeNull()
        ->and((float) $producto->precio_costo)->toBe(1234.50)
        ->and((float) $producto->precio_venta)->toBe(1999.99)
        ->and($producto->stock)->toBe(4);
});

test('importa texto pegado desde Excel con tabulaciones', function () {
    $admin = adminImportacion();

    $pegado = "nombre\tprecio_costo\tprecio_venta\tstock_minimo\tstock_inicial\n"
        ."Martillo Galponero\t2.500,00\t3.800,00\t2\t6\n";

    importar($this, $admin, $pegado);

    $producto = Producto::where('nombre', 'Martillo Galponero')->first();

    expect($producto)->not->toBeNull()
        ->and((float) $producto->precio_costo)->toBe(2500.00)
        ->and($producto->stock)->toBe(6);
});

test('acepta un archivo CSV subido y también datos sin encabezado', function () {
    $admin = adminImportacion();

    $archivo = UploadedFile::fake()->createWithContent(
        'productos.csv',
        "Bombacha de campo;1200;1800;5;10\n"
    );

    $this->actingAs($admin)->post(route('productos.importar.analizar'), [
        'archivo' => $archivo,
    ])->assertRedirect(route('productos.importar.previsualizar'));

    $preview = $this->actingAs($admin)->get(route('productos.importar.previsualizar'));
    $preview->assertOk()->assertSee('Bombacha de campo')->assertSee('Nuevo');

    $this->actingAs($admin)->post(route('productos.importar.confirmar'), [
        'token' => $preview->viewData('token'),
    ])->assertRedirect(route('productos.index'));

    expect(Producto::where('nombre', 'Bombacha de campo')->first()->stock)->toBe(10);
});

test('si el producto ya existe actualiza precios y stock mínimo pero no el stock', function () {
    $admin = adminImportacion();

    $producto = Producto::factory()->create([
        'nombre' => 'Bombacha de campo',
        'precio_costo' => 100,
        'precio_venta' => 150,
        'stock' => 7,
        'stock_minimo' => 1,
        'activo' => true,
    ]);

    // Mismo nombre con otra capitalización: se considera el mismo producto.
    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."BOMBACHA DE CAMPO;130;190;4;50\n";

    analizarImportacion($this, $admin, $csv)->assertRedirect(route('productos.importar.previsualizar'));

    $preview = $this->actingAs($admin)->get(route('productos.importar.previsualizar'));
    $preview->assertOk()
        ->assertSee('Actualiza')
        ->assertDontSee('Nuevo');

    $this->actingAs($admin)->post(route('productos.importar.confirmar'), [
        'token' => $preview->viewData('token'),
    ])->assertRedirect(route('productos.index'));

    $producto->refresh();

    expect(Producto::count())->toBe(1)
        ->and($producto->nombre)->toBe('Bombacha de campo')
        ->and((float) $producto->precio_costo)->toBe(130.00)
        ->and((float) $producto->precio_venta)->toBe(190.00)
        ->and($producto->stock_minimo)->toBe(4)
        ->and($producto->stock)->toBe(7) // El stock no se toca
        ->and(MovimientoStock::count())->toBe(0);
});

test('un producto existente inactivo se actualiza pero sigue inactivo', function () {
    $admin = adminImportacion();

    $producto = Producto::factory()->create([
        'nombre' => 'Producto Descontinuado',
        'precio_costo' => 100,
        'precio_venta' => 200,
        'stock' => 3,
        'stock_minimo' => 1,
        'activo' => false,
    ]);

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Producto Descontinuado;110;220;2;99\n";

    importar($this, $admin, $csv);

    $producto->refresh();

    expect($producto->activo)->toBeFalse()
        ->and((float) $producto->precio_costo)->toBe(110.00)
        ->and($producto->stock_minimo)->toBe(2)
        ->and($producto->stock)->toBe(3);
});

test('la importación es todo o nada cuando una fila tiene errores', function () {
    $admin = adminImportacion();

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Producto Válido;100;200;2;5\n"
        ."Producto con error;;300;1;0\n";

    $respuesta = analizarImportacion($this, $admin, $csv);

    $respuesta->assertRedirect(route('productos.importar'))
        ->assertSessionHas('filas_con_error');

    $this->assertDatabaseCount('productos', 0);
    $this->assertDatabaseCount('movimientos_stock', 0);
    $this->assertNull(session('importacion_productos'));

    // No existe una previsualización pendiente.
    $this->actingAs($admin)->get(route('productos.importar.previsualizar'))
        ->assertRedirect(route('productos.importar'))
        ->assertSessionHas('error');
});

test('detecta nombres repetidos dentro del mismo archivo', function () {
    $admin = adminImportacion();

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Producto Repetido;100;200;2;5\n"
        ."producto repetido;150;250;3;7\n";

    $respuesta = analizarImportacion($this, $admin, $csv);

    $respuesta->assertRedirect(route('productos.importar'))
        ->assertSessionHas('filas_con_error');

    $filas = session('filas_con_error');

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['linea'])->toBe(3)
        ->and($filas[0]['errores']['nombre'])->toContain('ya aparece en la fila 2');

    $this->assertDatabaseCount('productos', 0);
});

test('rechaza una plantilla a la que le faltan columnas obligatorias', function () {
    $admin = adminImportacion();

    $csv = "nombre;precio_venta;stock_minimo\n"
        ."Producto Incompleto;200;2\n";

    $respuesta = analizarImportacion($this, $admin, $csv);

    $respuesta->assertRedirect(route('productos.importar'))
        ->assertSessionHas('errores_importacion');

    expect(session('errores_importacion')[0])->toContain('precio_costo');
});

test('rechaza montos con más decimales de los que permite la configuración', function () {
    $admin = adminImportacion();

    Configuracion::actual()->update(['usa_centavos' => false]);

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Producto con centavos;120,50;200;2;0\n";

    $respuesta = analizarImportacion($this, $admin, $csv);

    $respuesta->assertRedirect(route('productos.importar'))
        ->assertSessionHas('filas_con_error');

    expect(session('filas_con_error')[0]['errores']['precio_costo'])->toContain('más decimales');
});

test('tolera una fila de título antes del encabezado', function () {
    $admin = adminImportacion();

    $csv = "Lista de precios proveedor\n"
        ."nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Bombacha de campo;1200;1800;5;10\n";

    importar($this, $admin, $csv);

    expect(Producto::where('nombre', 'Bombacha de campo')->first())->not->toBeNull();
});

test('no se puede confirmar una importación sin sesión o con un token inválido', function () {
    $admin = adminImportacion();

    $this->actingAs($admin)->post(route('productos.importar.confirmar'), [])
        ->assertRedirect(route('productos.importar'))
        ->assertSessionHas('error');

    analizarImportacion($this, $admin, "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\nProducto;100;200;1;0\n")
        ->assertRedirect(route('productos.importar.previsualizar'));

    $this->actingAs($admin)->post(route('productos.importar.confirmar'), [
        'token' => 'token-invalido',
    ])->assertForbidden();

    $this->assertDatabaseCount('productos', 0);
});

test('limita la cantidad de filas por importación', function () {
    $admin = adminImportacion();

    $lineas = ['nombre;precio_costo;precio_venta;stock_minimo;stock_inicial'];

    for ($i = 1; $i <= ImportacionProductos::MAX_FILAS + 1; $i++) {
        $lineas[] = "Producto {$i};100;200;1;0";
    }

    $respuesta = analizarImportacion($this, $admin, implode("\n", $lineas));

    $respuesta->assertRedirect(route('productos.importar'))
        ->assertSessionHas('errores_importacion');

    expect(session('errores_importacion')[0])->toContain((string) ImportacionProductos::MAX_FILAS);
});

test('el stock de todos los productos importados coincide con la suma de sus movimientos', function () {
    $admin = adminImportacion();

    $csv = "nombre;precio_costo;precio_venta;stock_minimo;stock_inicial\n"
        ."Producto A;100;200;1;10\n"
        ."Producto B;100;200;1;0\n"
        ."Producto C;100;200;1;25\n";

    importar($this, $admin, $csv);

    foreach (Producto::all() as $producto) {
        $entradas = (int) DB::table('movimientos_stock')
            ->where('producto_id', $producto->id)
            ->whereIn('tipo', [MovimientoStock::TIPO_INGRESO, MovimientoStock::TIPO_ANULACION])
            ->sum('cantidad');

        $salidas = (int) DB::table('movimientos_stock')
            ->where('producto_id', $producto->id)
            ->whereIn('tipo', [MovimientoStock::TIPO_VENTA, MovimientoStock::TIPO_DESCARTE])
            ->sum('cantidad');

        expect($producto->stock)->toBe($entradas - $salidas);
    }
});
