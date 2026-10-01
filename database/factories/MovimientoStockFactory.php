<?php

namespace Database\Factories;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MovimientoStock>
 */
class MovimientoStockFactory extends Factory
{
    protected $model = MovimientoStock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 50);
        $stockAnterior = fake()->numberBetween(0, 100);
        $stockPosterior = $stockAnterior + $cantidad;

        return [
            'producto_id' => Producto::factory(),
            'user_id' => User::factory(),
            'tipo' => 'ingreso',
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_posterior' => $stockPosterior,
            'observacion' => fake()->optional()->sentence(),
        ];
    }
}
