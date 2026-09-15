<?php

use App\Models\Caja;
use App\Models\User;

test('usuarios no autenticados son redirigidos al login al acceder a rutas de caja', function () {
    $caja = Caja::factory()->create();

    $this->get(route('cajas.index'))->assertRedirect(route('login'));
    $this->get(route('cajas.create'))->assertRedirect(route('login'));
    $this->post(route('cajas.store'), ['monto_inicial' => 1000])->assertRedirect(route('login'));
    $this->get(route('cajas.show', $caja))->assertRedirect(route('login'));
    $this->post(route('cajas.cerrar', $caja), ['monto_real' => 1000])->assertRedirect(route('login'));
});

test('un vendedor autenticado puede abrir una caja con monto inicial', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $response = $this->actingAs($vendedor)->post(route('cajas.store'), [
        'monto_inicial' => 1500.50,
    ]);

    $caja = Caja::where('user_id', $vendedor->id)->first();
    expect($caja)->not->toBeNull()
        ->and((float) $caja->monto_inicial)->toBe(1500.50)
        ->and((float) $caja->monto_esperado)->toBe(1500.50)
        ->and($caja->estado)->toBe('abierta')
        ->and($caja->fecha_cierre)->toBeNull()
        ->and($caja->monto_real)->toBeNull()
        ->and($caja->diferencia)->toBeNull();

    $response->assertRedirect(route('cajas.show', $caja));
    $response->assertSessionHas('success');
});

test('un vendedor no puede abrir una segunda caja si ya tiene una abierta', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaExistente = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    // Intentar acceder al formulario de apertura
    $this->actingAs($vendedor)
        ->get(route('cajas.create'))
        ->assertRedirect(route('cajas.index'))
        ->assertSessionHas('error');

    // Intentar enviar la solicitud de apertura por POST
    $response = $this->actingAs($vendedor)->post(route('cajas.store'), [
        'monto_inicial' => 2000.00,
    ]);

    $response->assertRedirect(route('cajas.index'));
    $response->assertSessionHas('error');

    // Comprobar que solo existe una caja para este usuario
    expect(Caja::where('user_id', $vendedor->id)->count())->toBe(1);
});

test('un vendedor puede ver los detalles de su propia caja', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1250.00,
        'monto_esperado' => 1250.00,
    ]);

    $response = $this->actingAs($vendedor)->get(route('cajas.show', $caja));

    $response->assertStatus(200);
    $response->assertSee('Detalle de Caja #' . $caja->id);
    $response->assertSee('1.250,00');
    $response->assertSee('Abierta');
    $response->assertSee('Confirmar Cierre de Caja');
});

test('un vendedor puede cerrar su propia caja y la diferencia se calcula correctamente en el backend', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    // Caso 1: Cierre exacto
    $cajaExacta = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    $response = $this->actingAs($vendedor)->post(route('cajas.cerrar', $cajaExacta), [
        'monto_real' => 1000.00,
    ]);

    $response->assertRedirect(route('cajas.show', $cajaExacta));
    $cajaExacta->refresh();
    expect($cajaExacta->estaCerrada())->toBeTrue()
        ->and((float) $cajaExacta->monto_real)->toBe(1000.00)
        ->and((float) $cajaExacta->diferencia)->toBe(0.00)
        ->and($cajaExacta->fecha_cierre)->not->toBeNull();

    // Caso 2: Cierre con sobrante (monto_real > monto_esperado)
    $cajaSobrante = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 2000.00,
        'monto_esperado' => 2000.00,
    ]);

    $this->actingAs($vendedor)->post(route('cajas.cerrar', $cajaSobrante), [
        'monto_real' => 2150.75,
    ]);

    $cajaSobrante->refresh();
    expect($cajaSobrante->estaCerrada())->toBeTrue()
        ->and((float) $cajaSobrante->monto_real)->toBe(2150.75)
        ->and((float) $cajaSobrante->diferencia)->toBe(150.75);

    // Caso 3: Cierre con faltante (monto_real < monto_esperado)
    $cajaFaltante = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 3000.00,
        'monto_esperado' => 3000.00,
    ]);

    $this->actingAs($vendedor)->post(route('cajas.cerrar', $cajaFaltante), [
        'monto_real' => 2850.00,
    ]);

    $cajaFaltante->refresh();
    expect($cajaFaltante->estaCerrada())->toBeTrue()
        ->and((float) $cajaFaltante->monto_real)->toBe(2850.00)
        ->and((float) $cajaFaltante->diferencia)->toBe(-150.00);
});

test('no se puede cerrar una caja que ya se encuentra cerrada', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaCerrada = Caja::factory()->cerrada(1000.00)->create([
        'user_id' => $vendedor->id,
    ]);

    $response = $this->actingAs($vendedor)->post(route('cajas.cerrar', $cajaCerrada), [
        'monto_real' => 1200.00,
    ]);

    $response->assertStatus(403);
});

test('un vendedor no puede ver ni cerrar la caja de otro usuario', function () {
    $vendedor1 = User::factory()->create();
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create();
    $vendedor2->assignRole('vendedor');

    $cajaVendedor2 = Caja::factory()->create([
        'user_id' => $vendedor2->id,
        'estado' => 'abierta',
        'monto_inicial' => 1000.00,
        'monto_esperado' => 1000.00,
    ]);

    // Vendedor 1 intenta ver la caja de Vendedor 2
    $this->actingAs($vendedor1)
        ->get(route('cajas.show', $cajaVendedor2))
        ->assertStatus(403);

    // Vendedor 1 intenta cerrar la caja de Vendedor 2
    $this->actingAs($vendedor1)
        ->post(route('cajas.cerrar', $cajaVendedor2), ['monto_real' => 1000.00])
        ->assertStatus(403);
});

test('un administrador puede ver el listado de todas las cajas y el detalle de cualquier caja', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor1 = User::factory()->create(['name' => 'Vendedor Juan']);
    $vendedor1->assignRole('vendedor');

    $vendedor2 = User::factory()->create(['name' => 'Vendedor Pedro']);
    $vendedor2->assignRole('vendedor');

    $caja1 = Caja::factory()->create([
        'user_id' => $vendedor1->id,
        'monto_inicial' => 1100.00,
        'monto_esperado' => 1100.00,
    ]);

    $caja2 = Caja::factory()->create([
        'user_id' => $vendedor2->id,
        'monto_inicial' => 2200.00,
        'monto_esperado' => 2200.00,
    ]);

    // El admin ve la lista general con las cajas de ambos vendedores
    $responseIndex = $this->actingAs($admin)->get(route('cajas.index'));
    $responseIndex->assertStatus(200);
    $responseIndex->assertSee('Vendedor Juan');
    $responseIndex->assertSee('Vendedor Pedro');

    // El admin puede ver los detalles de cualquier caja
    $this->actingAs($admin)->get(route('cajas.show', $caja1))->assertStatus(200)->assertSee('Vendedor Juan');
    $this->actingAs($admin)->get(route('cajas.show', $caja2))->assertStatus(200)->assertSee('Vendedor Pedro');
});

test('un administrador no puede cerrar la caja de otro usuario en esta etapa', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $cajaVendedor = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
        'monto_inicial' => 1500.00,
        'monto_esperado' => 1500.00,
    ]);

    // El admin intenta cerrar la caja del vendedor
    $response = $this->actingAs($admin)->post(route('cajas.cerrar', $cajaVendedor), [
        'monto_real' => 1500.00,
    ]);

    $response->assertStatus(403);
    $cajaVendedor->refresh();
    expect($cajaVendedor->estaAbierta())->toBeTrue();
});

test('un administrador puede cerrar su propia caja abierta', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cajaAdmin = Caja::factory()->create([
        'user_id' => $admin->id,
        'estado' => 'abierta',
        'monto_inicial' => 5000.00,
        'monto_esperado' => 5000.00,
    ]);

    $response = $this->actingAs($admin)->post(route('cajas.cerrar', $cajaAdmin), [
        'monto_real' => 5000.00,
    ]);

    $response->assertRedirect(route('cajas.show', $cajaAdmin));
    $cajaAdmin->refresh();
    expect($cajaAdmin->estaCerrada())->toBeTrue()
        ->and((float) $cajaAdmin->diferencia)->toBe(0.00);
});

test('validaciones rechazan monto inicial o real vacios o negativos', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    // Validación monto inicial
    $responseApertura = $this->actingAs($vendedor)->post(route('cajas.store'), [
        'monto_inicial' => -100,
    ]);
    $responseApertura->assertSessionHasErrors(['monto_inicial']);

    $caja = Caja::factory()->create([
        'user_id' => $vendedor->id,
        'estado' => 'abierta',
    ]);

    // Validación monto real
    $responseCierre = $this->actingAs($vendedor)->post(route('cajas.cerrar', $caja), [
        'monto_real' => -50,
    ]);
    $responseCierre->assertSessionHasErrors(['monto_real']);
});

test('no se puede eliminar un usuario con registros de caja asociados para preservar el historial', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    Caja::factory()->create([
        'user_id' => $vendedor->id,
    ]);

    $response = $this->actingAs($admin)->delete(route('usuarios.destroy', $vendedor));

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('error');

    // Comprobar que el usuario no fue eliminado
    $this->assertDatabaseHas('users', [
        'id' => $vendedor->id,
    ]);
});
