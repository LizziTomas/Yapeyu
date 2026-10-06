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
        ], [
            'nombre_local.required' => 'El nombre del local es obligatorio.',
            'nombre_local.max' => 'El nombre del local no puede superar los 100 caracteres.',
        ]);

        Configuracion::actual()->update($datos);

        return redirect()->route('configuracion.edit')->with('success', 'Configuración guardada correctamente.');
    }
}
