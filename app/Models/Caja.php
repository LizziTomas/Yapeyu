<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'cajas';

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'fecha_apertura',
        'fecha_cierre',
        'monto_inicial',
        'monto_esperado',
        'monto_real',
        'diferencia',
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
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'monto_inicial' => 'decimal:2',
            'monto_esperado' => 'decimal:2',
            'monto_real' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    /**
     * Relación con el usuario titular de la caja.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con las ventas realizadas en esta sesión de caja.
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Determina si la caja se encuentra actualmente abierta.
     */
    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    /**
     * Determina si la caja se encuentra cerrada.
     */
    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    /**
     * Suma total de ventas completadas en efectivo para esta caja.
     */
    public function totalVentasEfectivo(): float
    {
        return (float) $this->ventas()
            ->where('estado', 'completada')
            ->where('medio_pago', 'efectivo')
            ->sum('total');
    }

    /**
     * Suma total de ventas completadas por transferencia para esta caja.
     */
    public function totalVentasTransferencia(): float
    {
        return (float) $this->ventas()
            ->where('estado', 'completada')
            ->where('medio_pago', 'transferencia')
            ->sum('total');
    }

    /**
     * Suma total de ventas completadas con tarjeta para esta caja.
     */
    public function totalVentasTarjeta(): float
    {
        return (float) $this->ventas()
            ->where('estado', 'completada')
            ->where('medio_pago', 'tarjeta')
            ->sum('total');
    }

    /**
     * Total general vendido en todos los medios de pago (únicamente ventas completadas).
     */
    public function totalVendido(): float
    {
        return (float) $this->ventas()
            ->where('estado', 'completada')
            ->sum('total');
    }

    /**
     * Dinero físico esperado en la caja (Monto inicial + Ventas en efectivo).
     * Si la caja se encuentra cerrada, retorna el monto_esperado congelado en el cierre.
     */
    public function dineroEsperado(): float
    {
        if ($this->estaCerrada() && $this->monto_esperado !== null) {
            return (float) $this->monto_esperado;
        }

        return (float) $this->monto_inicial + $this->totalVentasEfectivo();
    }

    /**
     * Scope para filtrar cajas en estado abierta.
     */
    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', 'abierta');
    }

    /**
     * Scope para filtrar cajas en estado cerrada.
     */
    public function scopeCerradas(Builder $query): Builder
    {
        return $query->where('estado', 'cerrada');
    }
}
