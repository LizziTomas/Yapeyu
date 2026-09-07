<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Limpiar caché de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear los permisos iniciales del sistema
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

        // 2. Crear los roles base
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $vendedorRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);

        // 3. Asignar permisos a los roles
        $adminRole->syncPermissions($permisos);
        $vendedorRole->syncPermissions(['ver productos']);

        // 4. Migrar usuarios existentes con la columna role hacia Spatie
        if (Schema::hasColumn('users', 'role')) {
            $users = User::all();
            foreach ($users as $user) {
                if ($user->role === 'admin') {
                    $user->assignRole('admin');
                } else {
                    $user->assignRole('vendedor');
                }
            }

            // 5. Eliminar la columna role de la tabla users una vez migrados los datos
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('vendedor')->after('password');
            });

            $users = User::all();
            foreach ($users as $user) {
                $roleName = $user->getRoleNames()->first() ?? 'vendedor';
                $user->update(['role' => $roleName]);
            }
        }
    }
};
