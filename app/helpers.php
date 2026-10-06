<?php

use App\Models\Configuracion;

if (! function_exists('configuracion')) {
    /**
     * Acceso corto a la configuración del local desde vistas y validaciones.
     */
    function configuracion(): Configuracion
    {
        return Configuracion::actual();
    }
}

if (! function_exists('monto')) {
    /**
     * Formatea un monto en pesos (sin el signo $) con o sin centavos según la configuración.
     * Ejemplo: 1234.5 → "1.234,50" con centavos, "1.235" sin centavos.
     */
    function monto(float|int|string|null $valor): string
    {
        return number_format((float) $valor, configuracion()->decimales(), ',', '.');
    }
}
