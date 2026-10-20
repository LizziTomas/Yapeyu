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
     * Determina si el usuario puede anular una venta existente.
     * Requiere el permiso 'anular ventas' y que la venta siga completada.
     * El administrador puede anular cualquier venta, esté su caja abierta o cerrada.
     * El vendedor solo puede anular sus propias ventas mientras su caja siga abierta.
     */
    public function anular(User $user, Venta $venta): bool
    {
        if (! $user->can('anular ventas') || ! $venta->esCompletada()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $venta->user_id
            && $venta->caja
            && $venta->caja->estaAbierta();
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
