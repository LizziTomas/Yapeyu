<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    /**
     * Muestra el formulario de configuración del local (solo administradores).
     */
    public function edit(): View
    {
        return view('configuracion.edit', [
            'configuracion' => Configuracion::actual(),
        ]);
    }

    /**
     * Guarda el nombre del local y si los montos usan centavos.
     */
    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre_local' => ['required', 'string', 'max:100'],
            'usa_centavos' => ['required', 'boolean'],
            'avisos_stock' => ['sometimes', 'required', 'boolean'],
            'resumen_stock_dias' => ['sometimes', 'required', 'integer', 'min:1', 'max:30'],
        ], [
            'nombre_local.required' => 'El nombre del local es obligatorio.',
            'nombre_local.max' => 'El nombre del local no puede superar los 100 caracteres.',
            'resumen_stock_dias.required' => 'Indicá cada cuántos días se envía el resumen de stock.',
            'resumen_stock_dias.integer' => 'La frecuencia del resumen debe ser un número entero de días.',
            'resumen_stock_dias.min' => 'El resumen se puede enviar como mínimo cada 1 día.',
            'resumen_stock_dias.max' => 'El resumen se puede enviar como máximo cada 30 días.',
        ]);

        Configuracion::actual()->update($datos);

        return redirect()->route('configuracion.edit')->with('success', 'Configuración guardada correctamente.');
    }
}
