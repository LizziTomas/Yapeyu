<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos definidos
        $permisos = [
            'ver productos',
            'crear productos',
            'editar productos',
            'desactivar productos',
            'ver usuarios',
            'crear usuarios',
            'editar usuarios',
            'eliminar usuarios',
            'ver cajas',
            'abrir cajas',
            'cerrar cajas',
            'ver ventas',
            'crear ventas',
            'ver movimientos stock',
            'crear ingresos stock',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Rol Admin con todos los permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permisos);

        // Rol Vendedor con permisos de consulta, caja, ventas y consulta de movimientos de stock
        $vendedorRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedorRole->syncPermissions([
            'ver productos',
            'ver cajas',
            'abrir cajas',
            'cerrar cajas',
            'ver ventas',
            'crear ventas',
            'ver movimientos stock',
        ]);
    }
}
