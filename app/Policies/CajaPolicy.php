<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;

class CajaPolicy
{
    /**
     * Determina si el usuario puede ver la lista de cajas.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ver cajas');
    }

    /**
     * Determina si el usuario puede ver los detalles de una caja específica.
     * Un administrador puede ver cualquier caja; un vendedor solo sus propias cajas.
     */
    public function view(User $user, Caja $caja): bool
    {
        if (! $user->can('ver cajas')) {
            return false;
        }

        return $user->isAdmin() || $user->id === $caja->user_id;
    }

    /**
     * Determina si el usuario puede abrir una nueva caja.
     */
    public function create(User $user): bool
    {
        return $user->can('abrir cajas');
    }

    /**
     * Determina si el usuario puede cerrar una caja específica.
     * En esta etapa, el usuario (administrador o vendedor) solo puede cerrar su propia caja abierta.
     */
    public function close(User $user, Caja $caja): bool
    {
        if (! $user->can('cerrar cajas')) {
            return false;
        }

        if (! $caja->estaAbierta()) {
            return false;
        }

        return $user->id === $caja->user_id;
    }
}
