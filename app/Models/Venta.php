<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'ventas';

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'caja_id',
        'user_id',
        'total',
        'medio_pago',
        'estado',
    ];

    /**
     * Conversiones de tipos de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    /**
     * Relación con el usuario (vendedor) que registró la venta.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con la sesión de caja en la que se efectuó la venta.
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Relación con los detalles y renglones de la venta.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    /**
     * Determina si la venta se encuentra en estado completada.
     */
    public function esCompletada(): bool
    {
        return $this->estado === 'completada';
    }
}
