<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Caja>
 */
class CajaFactory extends Factory
{
    protected $model = Caja::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $montoInicial = fake()->randomFloat(2, 500, 10000);

        return [
            'user_id' => User::factory(),
            'fecha_apertura' => now(),
            'fecha_cierre' => null,
            'monto_inicial' => $montoInicial,
            'monto_esperado' => $montoInicial,
            'monto_real' => null,
            'diferencia' => null,
            'estado' => 'abierta',
        ];
    }

    /**
     * Estado para caja cerrada.
     */
    public function cerrada(?float $montoReal = null): static
    {
        return $this->state(function (array $attributes) use ($montoReal) {
            $esperado = $attributes['monto_esperado'] ?? $attributes['monto_inicial'];
            $real = $montoReal ?? $esperado;
            $diferencia = round($real - $esperado, 2);

            return [
                'fecha_cierre' => now(),
                'monto_real' => $real,
                'diferencia' => $diferencia,
                'estado' => 'cerrada',
            ];
        });
    }
}
