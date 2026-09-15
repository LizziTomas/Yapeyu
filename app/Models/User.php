<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    
    use HasFactory, Notifiable, HasRoles;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determina si el usuario tiene el rol administrador mediante Spatie.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Determina si el usuario tiene el rol vendedor mediante Spatie.
     */
    public function isVendedor(): bool
    {
        return $this->hasRole('vendedor');
    }

    /**
     * Relación con las cajas registradas por el usuario.
     */
    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    /**
     * Retorna la caja actualmente abierta del usuario, si existe.
     */
    public function cajaAbierta(): ?Caja
    {
        return $this->cajas()->where('estado', 'abierta')->first();
    }
}

