<?php

namespace App\Policies;

use App\Models\MovimientoStock;
use App\Models\User;

class MovimientoStockPolicy
{
    /**
     * Determina si el usuario puede ver la lista de movimientos de stock.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ver movimientos stock');
    }

    /**
     * Determina si el usuario puede ver los detalles de un movimiento de stock específico.
     */
    public function view(User $user, MovimientoStock $movimientoStock): bool
    {
        return $user->can('ver movimientos stock');
    }

    /**
     * Determina si el usuario puede registrar un nuevo ingreso de mercadería.
     */
    public function create(User $user): bool
    {
        return $user->can('crear ingresos stock');
    }

    /**
     * Los movimientos de stock son históricos y no pueden modificarse.
     */
    public function update(User $user, MovimientoStock $movimientoStock): bool
    {
        return false;
    }

    /**
     * Los movimientos de stock son históricos y no pueden eliminarse.
     */
    public function delete(User $user, MovimientoStock $movimientoStock): bool
    {
        return false;
    }
}
