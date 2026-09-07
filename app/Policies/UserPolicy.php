<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ver usuarios');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('ver usuarios');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('crear usuarios');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('editar usuarios');
    }

    /**
     * Determine whether the user can delete the model.
     * Un admin NO puede eliminarse a sí mismo.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('eliminar usuarios') && $user->id !== $model->id;
    }
}
