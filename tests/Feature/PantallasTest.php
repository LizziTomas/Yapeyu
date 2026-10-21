<?php

use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

// Prueba de humo: con los datos de demostración cargados, cada pantalla tiene que abrir
// sin errores del servidor para cada rol.

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, DemoSeeder::class]);
});

function urlsDelSistema(User $usuario): array
{
    return [
        route('dashboard'),
        route('productos.index'),
        route('productos.create'),
        route('productos.edit', Producto::first()),
        route('movimientos-stock.index'),
        route('movimientos-stock.create'),
        route('movimientos-stock.descartar'),
        route('movimientos-stock.show', MovimientoStock::first()),
        route('cajas.index'),
        route('cajas.create'),
        route('cajas.show', Caja::first()),
        route('cajas.ticket', Caja::first()),
        route('ventas.index'),
        route('ventas.create'),
        route('ventas.show', Venta::first()),
        route('reportes.ventas'),
        route('reportes.ventas.exportar'),
        route('usuarios.index'),
        route('usuarios.create'),
        route('usuarios.edit', $usuario),
        route('configuracion.edit'),
        route('profile.edit'),
        route('chat-ia.index'),
    ];
}

test('todas las pantallas abren sin errores para el administrador, con y sin centavos', function (bool $usaCentavos) {
    Configuracion::actual()->update(['usa_centavos' => $usaCentavos]);
    $admin = User::role('admin')->first();

    foreach (urlsDelSistema($admin) as $url) {
        $estado = $this->actingAs($admin)->get($url)->getStatusCode();
        // Se aceptan redirecciones (por ejemplo, "Nueva venta" sin caja abierta lleva a Cajas)
        $this->assertLessThan(400, $estado, "La pantalla {$url} respondió {$estado}");
    }
})->with([
    'con centavos' => true,
    'sin centavos' => false,
]);

test('el vendedor nunca recibe un error del servidor y no entra a las pantallas de administración', function () {
    $vendedor = User::role('vendedor')->first();

    foreach (urlsDelSistema($vendedor) as $url) {
        $estado = $this->actingAs($vendedor)->get($url)->getStatusCode();
        $this->assertLessThan(500, $estado, "La pantalla {$url} respondió {$estado}");
    }

    foreach ([route('usuarios.index'), route('productos.create'), route('configuracion.edit')] as $url) {
        $this->actingAs($vendedor)->get($url)->assertForbidden();
    }
});
