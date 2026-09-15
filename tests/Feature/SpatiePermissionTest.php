<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('spatie roles and permissions are properly configured', function () {
    $adminRole = Role::findByName('admin');
    $vendedorRole = Role::findByName('vendedor');

    expect($adminRole)->not->toBeNull()
        ->and($vendedorRole)->not->toBeNull()
        ->and($adminRole->hasPermissionTo('ver productos'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('crear productos'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('editar productos'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('desactivar productos'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('ver usuarios'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('crear usuarios'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('editar usuarios'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('eliminar usuarios'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('ver cajas'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('abrir cajas'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('cerrar cajas'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('ver ventas'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('crear ventas'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('ver productos'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('crear productos'))->toBeFalse()
        ->and($vendedorRole->hasPermissionTo('ver usuarios'))->toBeFalse()
        ->and($vendedorRole->hasPermissionTo('ver cajas'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('abrir cajas'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('cerrar cajas'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('ver ventas'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('crear ventas'))->toBeTrue();
});

test('user with admin role inherits all admin permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->hasRole('vendedor'))->toBeFalse()
        ->and($admin->can('ver productos'))->toBeTrue()
        ->and($admin->can('crear productos'))->toBeTrue()
        ->and($admin->can('editar productos'))->toBeTrue()
        ->and($admin->can('desactivar productos'))->toBeTrue()
        ->and($admin->can('ver usuarios'))->toBeTrue()
        ->and($admin->can('crear usuarios'))->toBeTrue()
        ->and($admin->can('editar usuarios'))->toBeTrue()
        ->and($admin->can('eliminar usuarios'))->toBeTrue()
        ->and($admin->can('ver cajas'))->toBeTrue()
        ->and($admin->can('abrir cajas'))->toBeTrue()
        ->and($admin->can('cerrar cajas'))->toBeTrue()
        ->and($admin->can('ver ventas'))->toBeTrue()
        ->and($admin->can('crear ventas'))->toBeTrue();
});

test('user with vendedor role has product viewing and own caja permissions', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    expect($vendedor->hasRole('vendedor'))->toBeTrue()
        ->and($vendedor->hasRole('admin'))->toBeFalse()
        ->and($vendedor->can('ver productos'))->toBeTrue()
        ->and($vendedor->can('crear productos'))->toBeFalse()
        ->and($vendedor->can('editar productos'))->toBeFalse()
        ->and($vendedor->can('desactivar productos'))->toBeFalse()
        ->and($vendedor->can('ver usuarios'))->toBeFalse()
        ->and($vendedor->can('crear usuarios'))->toBeFalse()
        ->and($vendedor->can('editar usuarios'))->toBeFalse()
        ->and($vendedor->can('eliminar usuarios'))->toBeFalse()
        ->and($vendedor->can('ver cajas'))->toBeTrue()
        ->and($vendedor->can('abrir cajas'))->toBeTrue()
        ->and($vendedor->can('cerrar cajas'))->toBeTrue()
        ->and($vendedor->can('ver ventas'))->toBeTrue()
        ->and($vendedor->can('crear ventas'))->toBeTrue();
});
