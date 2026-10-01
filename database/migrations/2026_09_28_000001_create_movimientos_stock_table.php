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
     * Ejecuta las migraciones creando la tabla movimientos_stock y los permisos asociados.
     */
    public function up(): void
    {
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('tipo', 30)->default('ingreso');
            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_posterior');
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
        });

        // Registrar permisos para el módulo de movimientos de stock
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $stockPermisos = [
            'ver movimientos stock',
            'crear ingresos stock',
        ];

        foreach ($stockPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($stockPermisos);

        $vendedorRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedorRole->givePermissionTo(['ver movimientos stock']);
    }

    /**
     * Revierte las migraciones eliminando la tabla movimientos_stock.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
