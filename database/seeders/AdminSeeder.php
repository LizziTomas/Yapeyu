<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Crea un usuario administrador para el entorno local.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@local.test'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
            ]
        );

        $user->assignRole('admin');
    }
}