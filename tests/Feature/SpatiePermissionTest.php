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
        ->and($vendedorRole->hasPermissionTo('ver productos'))->toBeTrue()
        ->and($vendedorRole->hasPermissionTo('crear productos'))->toBeFalse()
        ->and($vendedorRole->hasPermissionTo('ver usuarios'))->toBeFalse();
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
        ->and($admin->can('eliminar usuarios'))->toBeTrue();
});

test('user with vendedor role only has product viewing permission', function () {
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
        ->and($vendedor->can('eliminar usuarios'))->toBeFalse();
});
