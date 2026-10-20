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
     * Agrega los campos de auditoría de anulación a la tabla de ventas
     * y registra el permiso para anular ventas.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('motivo_anulacion', 255)->nullable()->after('estado');
            $table->foreignId('anulada_por')
                ->nullable()
                ->after('motivo_anulacion')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulada_at')->nullable()->after('anulada_por');
        });

        // Registro del permiso para anular ventas
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => 'anular ventas', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($permiso);
        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web'])->givePermissionTo($permiso);
    }

    /**
     * Revierte la migración eliminando los campos de anulación y el permiso.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['anulada_por']);
            $table->dropColumn(['motivo_anulacion', 'anulada_por', 'anulada_at']);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'anular ventas')->where('guard_name', 'web')->delete();
    }
};
