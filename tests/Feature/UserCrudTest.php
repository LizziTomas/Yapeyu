<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('public registration always assigns vendedor role even if admin role is sent', function () {
    $response = $this->post('/register', [
        'name' => 'Vendedor Prueba',
        'email' => 'vendedor_test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin', // Intento de hackear el rol en registro público
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'vendedor_test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('vendedor')
        ->and($user->isVendedor())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse();
});

test('unauthenticated users cannot access user management', function () {
    $response = $this->get(route('usuarios.index'));
    $response->assertRedirect(route('login'));
});

test('vendedor cannot access user management (returns 403)', function () {
    $vendedor = User::factory()->create([
        'role' => 'vendedor',
    ]);

    $response = $this->actingAs($vendedor)->get(route('usuarios.index'));
    $response->assertStatus(403);

    $responseCreate = $this->actingAs($vendedor)->get(route('usuarios.create'));
    $responseCreate->assertStatus(403);
});

test('admin can view user list', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get(route('usuarios.index'));
    $response->assertStatus(200);
    $response->assertSee('Gestión de Usuarios');
});

test('admin can create a new user with any role', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->post(route('usuarios.store'), [
        'name' => 'Nuevo Empleado',
        'email' => 'empleado@example.com',
        'role' => 'vendedor',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('success');

    $newUser = User::where('email', 'empleado@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('Nuevo Empleado')
        ->and($newUser->role)->toBe('vendedor')
        ->and(Hash::check('password123', $newUser->password))->toBeTrue();
});

test('admin can update user and change role', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $userToEdit = User::factory()->create([
        'name' => 'Nombre Viejo',
        'email' => 'viejo@example.com',
        'role' => 'vendedor',
    ]);

    $response = $this->actingAs($admin)->put(route('usuarios.update', $userToEdit), [
        'name' => 'Nombre Nuevo',
        'email' => 'nuevo@example.com',
        'role' => 'admin',
    ]);

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('success');

    $userToEdit->refresh();
    expect($userToEdit->name)->toBe('Nombre Nuevo')
        ->and($userToEdit->email)->toBe('nuevo@example.com')
        ->and($userToEdit->role)->toBe('admin');
});

test('admin CANNOT delete their own account', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->delete(route('usuarios.destroy', $admin));

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('error');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
    ]);
});

test('admin CAN delete another user', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $targetUser = User::factory()->create([
        'role' => 'vendedor',
    ]);

    $response = $this->actingAs($admin)->delete(route('usuarios.destroy', $targetUser));

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ]);
});
