<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costo = fake()->randomFloat(2, 50, 5000);
        $venta = round($costo * fake()->randomFloat(2, 1.2, 1.8), 2);

        return [
            'nombre' => fake()->unique()->words(2, true),
            'precio_costo' => $costo,
            'precio_venta' => $venta,
            'stock' => fake()->numberBetween(5, 100),
            'stock_minimo' => fake()->numberBetween(2, 10),
            'activo' => true,
        ];
    }

    /**
     * Estado para producto inactivo.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
