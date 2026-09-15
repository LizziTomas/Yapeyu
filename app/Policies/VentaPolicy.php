<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venta;

class VentaPolicy
{
    /**
     * Determina si el usuario puede ver la lista de ventas.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ver ventas');
    }

    /**
     * Determina si el usuario puede ver el detalle de una venta específica.
     * Un administrador puede ver cualquier venta; un vendedor solo sus propias ventas.
     */
    public function view(User $user, Venta $venta): bool
    {
        if (! $user->can('ver ventas')) {
            return false;
        }

        return $user->isAdmin() || $user->id === $venta->user_id;
    }

    /**
     * Determina si el usuario puede crear una nueva venta.
     * Requiere el permiso correspondiente y tener una caja actualmente abierta.
     */
    public function create(User $user): bool
    {
        return $user->can('crear ventas');
    }

    /**
     * Las ventas confirmadas no pueden editarse (representan registros históricos).
     */
    public function update(User $user, Venta $venta): bool
    {
        return false;
    }

    /**
     * Las ventas confirmadas no pueden eliminarse (representan registros históricos).
     */
    public function delete(User $user, Venta $venta): bool
    {
        return false;
    }
}
