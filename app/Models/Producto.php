<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'productos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'precio_costo',
        'precio_venta',
        'stock',
        'stock_minimo',
        'activo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Scope para filtrar únicamente productos activos.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Valor del stock actual a precio de costo.
     */
    public function getValorStockCostoAttribute(): float
    {
        return (float) ($this->stock * $this->precio_costo);
    }

    /**
     * Valor potencial de venta del stock actual.
     */
    public function getValorPotencialVentaAttribute(): float
    {
        return (float) ($this->stock * $this->precio_venta);
    }

    /**
     * Determina si el producto está en nivel de stock mínimo o inferior.
     */
    public function isStockBajo(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
