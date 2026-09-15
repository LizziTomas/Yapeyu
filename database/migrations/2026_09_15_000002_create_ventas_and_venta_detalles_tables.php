<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones creando las tablas ventas y venta_detalles.
     */
    public function up(): void
    {
        // Tabla de ventas de cabecera
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')
                ->constrained('cajas')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->string('medio_pago', 30);
            $table->string('estado', 20)->default('completada');
            $table->timestamps();
        });

        // Tabla de renglones y detalles históricos de cada venta
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')
                ->constrained('ventas')
                ->restrictOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        // Registrar permisos iniciales para el módulo de ventas
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $ventaPermisos = ['ver ventas', 'crear ventas'];
        foreach ($ventaPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($ventaPermisos);

        $vendedorRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedorRole->givePermissionTo($ventaPermisos);
    }

    /**
     * Revierte las migraciones eliminando las tablas.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
