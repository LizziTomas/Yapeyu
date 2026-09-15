<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('monto_inicial', 12, 2);
            $table->decimal('monto_esperado', 12, 2);
            $table->decimal('monto_real', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado', 20)->default('abierta');
            $table->timestamps();
        });

        // Registrar permisos iniciales para el módulo de cajas
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $cajaPermisos = ['ver cajas', 'abrir cajas', 'cerrar cajas'];
        foreach ($cajaPermisos as $permiso) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($cajaPermisos);

        $vendedorRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedorRole->givePermissionTo($cajaPermisos);
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
