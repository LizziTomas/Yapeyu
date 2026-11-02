<?php

use App\Mail\StockBajoMail;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

// Resumen periódico de stock bajo: el comando corre todos los días y decide
// si ya pasaron los días configurados desde el último envío.

function adminConCorreo(string $email = 'duenio@yapeyu.com'): User
{
    $admin = User::factory()->create(['email' => $email]);
    $admin->assignRole('admin');

    return $admin;
}

function hoyEnElLocal(): string
{
    return Carbon::now('America/Argentina/Buenos_Aires')->toDateString();
}

test('envía el resumen con los productos que están bajo el mínimo', function () {
    Mail::fake();
    adminConCorreo();

    Producto::factory()->create(['nombre' => 'Bajo Mínimo', 'stock' => 1, 'stock_minimo' => 5, 'activo' => true]);
    Producto::factory()->create(['nombre' => 'Stock Normal', 'stock' => 50, 'stock_minimo' => 5, 'activo' => true]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertSent(StockBajoMail::class, function (StockBajoMail $mail) {
        return $mail->hasTo('duenio@yapeyu.com')
            && count($mail->productos) === 1
            && $mail->productos[0]['nombre'] === 'Bajo Mínimo';
    });

    expect(Configuracion::actual()->resumen_stock_ultimo_envio)->not->toBeNull();
});

test('no envía el resumen si todavía no pasaron los días configurados', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 1, 'stock_minimo' => 5, 'activo' => true]);

    Configuracion::actual()->update([
        'avisos_stock' => true,
        'resumen_stock_dias' => 3,
        'resumen_stock_ultimo_envio' => hoyEnElLocal(),
    ]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertNothingSent();
});

test('envía el resumen cuando ya pasaron los días configurados', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 1, 'stock_minimo' => 5, 'activo' => true]);

    Configuracion::actual()->update([
        'avisos_stock' => true,
        'resumen_stock_dias' => 3,
        'resumen_stock_ultimo_envio' => Carbon::parse(hoyEnElLocal())->subDays(3)->toDateString(),
    ]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertSent(StockBajoMail::class);
});

test('la opción force ignora la frecuencia configurada', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 1, 'stock_minimo' => 5, 'activo' => true]);

    Configuracion::actual()->update([
        'avisos_stock' => true,
        'resumen_stock_dias' => 10,
        'resumen_stock_ultimo_envio' => hoyEnElLocal(),
    ]);

    $this->artisan('stock:resumen --force')->assertSuccessful();

    Mail::assertSent(StockBajoMail::class);
});

test('no envía nada si los avisos de stock están desactivados', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 1, 'stock_minimo' => 5, 'activo' => true]);

    Configuracion::actual()->update(['avisos_stock' => false]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertNothingSent();
});

test('no envía el resumen si no hay productos con stock bajo', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 50, 'stock_minimo' => 5, 'activo' => true]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertNothingSent();
    expect(Configuracion::actual()->resumen_stock_ultimo_envio)->toBeNull();
});

test('no tiene en cuenta los productos inactivos', function () {
    Mail::fake();
    adminConCorreo();
    Producto::factory()->create(['stock' => 1, 'stock_minimo' => 5, 'activo' => false]);

    $this->artisan('stock:resumen')->assertSuccessful();

    Mail::assertNothingSent();
});

test('el administrador configura los avisos y la frecuencia desde la pantalla', function () {
    $admin = adminConCorreo();

    $this->actingAs($admin)->patch(route('configuracion.update'), [
        'nombre_local' => 'Casa Yacobone',
        'usa_centavos' => '1',
        'avisos_stock' => '0',
        'resumen_stock_dias' => 7,
    ])->assertRedirect(route('configuracion.edit'));

    $configuracion = Configuracion::actual();

    expect($configuracion->avisos_stock)->toBeFalse()
        ->and($configuracion->resumen_stock_dias)->toBe(7);
});

test('la frecuencia del resumen se valida entre 1 y 30 días', function () {
    $admin = adminConCorreo();

    $this->actingAs($admin)->patch(route('configuracion.update'), [
        'nombre_local' => 'Casa Yacobone',
        'usa_centavos' => '1',
        'avisos_stock' => '1',
        'resumen_stock_dias' => 0,
    ])->assertSessionHasErrors('resumen_stock_dias');

    $this->actingAs($admin)->patch(route('configuracion.update'), [
        'nombre_local' => 'Casa Yacobone',
        'usa_centavos' => '1',
        'avisos_stock' => '1',
        'resumen_stock_dias' => 31,
    ])->assertSessionHasErrors('resumen_stock_dias');
});
