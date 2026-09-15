<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venta>
 */
class VentaFactory extends Factory
{
    protected $model = Venta::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'caja_id' => Caja::factory(),
            'total' => fake()->randomFloat(2, 500, 25000),
            'medio_pago' => fake()->randomElement(['efectivo', 'transferencia', 'tarjeta']),
            'estado' => 'completada',
        ];
    }
}
