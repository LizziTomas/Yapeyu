<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'nombre_local',
        'usa_centavos',
        'avisos_stock',
        'resumen_stock_dias',
        'resumen_stock_ultimo_envio',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usa_centavos' => 'boolean',
            'avisos_stock' => 'boolean',
            'resumen_stock_dias' => 'integer',
            'resumen_stock_ultimo_envio' => 'date',
        ];
    }

    /**
     * Devuelve la configuración del local. Si todavía no existe, la crea con valores por defecto.
     * once() la guarda en memoria durante la petición para no consultar la base en cada monto.
     */
    public static function actual(): self
    {
        return once(fn () => static::firstOrCreate([], [
            'nombre_local' => 'Casa Yacobone',
            'usa_centavos' => true,
            'avisos_stock' => true,
            'resumen_stock_dias' => 3,
        ]));
    }

    /**
     * Cantidad de decimales con la que se muestran y cargan los montos.
     */
    public function decimales(): int
    {
        return $this->usa_centavos ? 2 : 0;
    }

    /**
     * Valor del atributo "step" de los campos de dinero en los formularios.
     */
    public function paso(): string
    {
        return $this->usa_centavos ? '0.01' : '1';
    }
}
