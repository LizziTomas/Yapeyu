<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
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
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Rol Admin con todos los permisos
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permisos);

        // Rol Vendedor con permiso exclusivo de consulta
        $vendedorRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedorRole->syncPermissions(['ver productos']);
    }
}
