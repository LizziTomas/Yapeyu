<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    use HasFactory;

    /**
     * Tipos de movimiento de stock soportados por el sistema.
     */
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_VENTA = 'venta';

    public const TIPO_ANULACION = 'anulacion';

    public const TIPO_DESCARTE = 'descarte';

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'movimientos_stock';

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'producto_id',
        'user_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_posterior',
        'observacion',
    ];

    /**
     * Atributos casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'stock_anterior' => 'integer',
            'stock_posterior' => 'integer',
        ];
    }

    /**
     * Relación con el producto asociado al movimiento.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación con el usuario que registró el movimiento.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determina si el movimiento corresponde a un ingreso de mercadería.
     */
    public function esIngreso(): bool
    {
        return $this->tipo === self::TIPO_INGRESO;
    }

    /**
     * Determina si el movimiento corresponde a la salida de stock por una venta.
     */
    public function esVenta(): bool
    {
        return $this->tipo === self::TIPO_VENTA;
    }

    /**
     * Determina si el movimiento corresponde al estorno por anulación de venta.
     */
    public function esAnulacion(): bool
    {
        return $this->tipo === self::TIPO_ANULACION;
    }

    /**
     * Determina si el movimiento corresponde a un descarte de mercadería.
     */
    public function esDescarte(): bool
    {
        return $this->tipo === self::TIPO_DESCARTE;
    }

    /**
     * Determina si el movimiento incrementa el stock del producto.
     * Los ingresos y las anulaciones suman; las ventas y los descartes restan.
     */
    public function sumaStock(): bool
    {
        return $this->esIngreso() || $this->esAnulacion();
    }

    /**
     * Etiqueta legible del tipo de movimiento para las vistas.
     */
    public function tipoLabel(): string
    {
        return self::etiquetas()[$this->tipo] ?? ucfirst($this->tipo);
    }

    /**
     * Tipos de movimiento disponibles con su etiqueta legible.
     *
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::TIPO_INGRESO => 'Ingreso',
            self::TIPO_VENTA => 'Venta',
            self::TIPO_ANULACION => 'Anulación',
            self::TIPO_DESCARTE => 'Descarte',
        ];
    }
}
