<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalizarImportacionRequest;
use App\Models\Producto;
use App\Services\ImportacionProductos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Importación de productos desde Excel/CSV.
 *
 * Flujo: formulario → análisis y validación → previsualización → confirmación.
 * La importación es "todo o nada": si una fila tiene errores no se guarda nada.
 */
class ProductoImportacionController extends Controller
{
    /** Clave de sesión donde se guarda la importación analizada. */
    private const SESION = 'importacion_productos';

    /**
     * Formulario para subir un CSV o pegar datos desde Excel.
     */
    public function form(): View
    {
        Gate::authorize('create', Producto::class);

        return view('productos.importar');
    }

    /**
     * Descarga la plantilla CSV con BOM (para que Excel respete los acentos).
     */
    public function plantilla(): Response
    {
        Gate::authorize('create', Producto::class);

        $contenido = "\xEF\xBB\xBF".implode(';', ImportacionProductos::COLUMNAS)."\r\n";

        return response($contenido, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-productos.csv"',
        ]);
    }

    /**
     * Analiza el archivo o el texto pegado y, si está todo bien, muestra la previsualización.
     */
    public function analizar(AnalizarImportacionRequest $request, ImportacionProductos $importador): RedirectResponse
    {
        Gate::authorize('create', Producto::class);

        $lectura = $importador->leer($request->contenido());

        if ($lectura['errores'] !== []) {
            return redirect()->route('productos.importar')
                ->withInput()
                ->with('errores_importacion', $lectura['errores']);
        }

        $filas = $importador->analizar($lectura['filas']);

        if ($importador->hayErrores($filas)) {
            $conError = array_values(array_filter(
                $filas,
                fn (array $fila) => ($fila['errores'] ?? []) !== []
            ));

            return redirect()->route('productos.importar')
                ->withInput()
                ->with('filas_con_error', $conError);
        }

        $request->session()->put(self::SESION, [
            'filas' => $filas,
            'token' => Str::random(40),
            'creado' => now()->timestamp,
        ]);

        return redirect()->route('productos.importar.previsualizar');
    }

    /**
     * Muestra la tabla con lo que se va a crear y lo que se va a actualizar.
     */
    public function previsualizar(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Producto::class);

        $datos = $request->session()->get(self::SESION);

        if (! is_array($datos) || empty($datos['filas'])) {
            return redirect()->route('productos.importar')
                ->with('error', 'La importación expiró o ya se procesó. Volvé a subir el archivo.');
        }

        $filas = $datos['filas'];

        $resumen = [
            'total' => count($filas),
            'nuevos' => count(array_filter($filas, fn (array $fila) => $fila['estado'] === 'nuevo')),
            'actualiza' => count(array_filter($filas, fn (array $fila) => $fila['estado'] === 'actualiza')),
            'inactivos' => count(array_filter($filas, fn (array $fila) => $fila['estado'] === 'inactivo')),
            'unidades' => array_sum(array_map(
                fn (array $fila) => $fila['estado'] === 'nuevo' ? (int) $fila['stock_inicial'] : 0,
                $filas
            )),
        ];

        return view('productos.importar-previsualizar', [
            'filas' => $filas,
            'resumen' => $resumen,
            'token' => $datos['token'],
        ]);
    }

    /**
     * Confirma la importación y guarda todo en una sola transacción.
     */
    public function confirmar(Request $request, ImportacionProductos $importador): RedirectResponse
    {
        Gate::authorize('create', Producto::class);

        $datos = $request->session()->get(self::SESION);

        if (! is_array($datos) || empty($datos['filas'])) {
            return redirect()->route('productos.importar')
                ->with('error', 'La importación expiró o ya se procesó. Volvé a subir el archivo.');
        }

        if (! hash_equals((string) $datos['token'], (string) $request->input('token', ''))) {
            abort(403, 'La importación no es válida.');
        }

        try {
            $resumen = $importador->confirmar($datos['filas'], $request->user());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('productos.importar.previsualizar')
                ->with('error', 'No se pudo completar la importación. No se guardó ningún cambio.');
        }

        $request->session()->forget(self::SESION);

        $partes = ["{$resumen['creados']} ".($resumen['creados'] === 1 ? 'producto creado' : 'productos creados')];

        if ($resumen['actualizados'] > 0) {
            $partes[] = "{$resumen['actualizados']} ".($resumen['actualizados'] === 1 ? 'producto actualizado' : 'productos actualizados');
        }

        if ($resumen['movimientos'] > 0) {
            $partes[] = "{$resumen['movimientos']} ".($resumen['movimientos'] === 1 ? 'ingreso de stock registrado' : 'ingresos de stock registrados');
        }

        return redirect()->route('productos.index')
            ->with('success', 'Importación completada: '.implode(', ', $partes).'.');
    }
}
