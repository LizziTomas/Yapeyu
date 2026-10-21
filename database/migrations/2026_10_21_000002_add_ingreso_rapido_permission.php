<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Registra el permiso para vender mercadería que llega en el momento:
     * permite crear el producto (si no existe) y registrar su ingreso de stock
     * sin abrir el módulo de Movimientos de Stock.
     *
     * Es un permiso acotado a esa pantalla: no habilita la gestión completa de
     * productos ni los ingresos manuales de stock, que siguen reservados al admin.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => 'ingreso rapido en venta', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($permiso);
        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web'])->givePermissionTo($permiso);
    }

    /**
     * Revierte la migración eliminando el permiso.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'ingreso rapido en venta')->where('guard_name', 'web')->delete();
    }
};
