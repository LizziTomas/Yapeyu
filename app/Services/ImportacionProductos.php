<?php

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Motor de importación de productos desde CSV o texto pegado desde Excel.
 *
 * Lee el contenido, lo normaliza (encoding, separador, decimales), valida cada
 * fila y, al confirmar, crea o actualiza los productos en una sola transacción.
 *
 * Reglas acordadas:
 * - Si el producto ya existe (mismo nombre sin distinguir mayúsculas ni acentos),
 *   se actualizan precios y stock mínimo. El stock NO se modifica.
 * - La importación es "todo o nada": si una fila tiene errores, no se guarda nada.
 * - El stock inicial de un producto nuevo se registra como movimiento de ingreso,
 *   para que el stock siga siendo igual a la suma de los movimientos.
 */
class ImportacionProductos
{
    /** Máximo de productos por importación (evita sesiones enormes y cargas accidentales). */
    public const MAX_FILAS = 500;

    /** Columnas que se descargan en la plantilla, en orden. */
    public const COLUMNAS = ['nombre', 'precio_costo', 'precio_venta', 'stock_minimo', 'stock_inicial'];

    /** Columnas obligatorias para poder importar. */
    private const OBLIGATORIAS = ['nombre', 'precio_costo', 'precio_venta', 'stock_minimo'];

    /**
     * Alias aceptados para cada columna. Se comparan normalizados (sin acentos,
     * en minúsculas y con "_" en lugar de espacios).
     *
     * @var array<string, array<int, string>>
     */
    private const ALIAS = [
        'nombre' => ['nombre', 'producto', 'descripcion', 'detalle', 'articulo'],
        'precio_costo' => ['precio_costo', 'costo', 'p_costo', 'precio_de_costo', 'precio_compra'],
        'precio_venta' => ['precio_venta', 'venta', 'precio', 'p_venta', 'precio_de_venta'],
        'stock_minimo' => ['stock_minimo', 'minimo', 'stock_min', 'min'],
        'stock_inicial' => ['stock_inicial', 'inicial', 'stock', 'cantidad', 'cant'],
    ];

    /**
     * Lee y valida el contenido de un CSV o de un texto pegado desde Excel.
     *
     * Devuelve las filas normalizadas (con sus errores por columna) y una lista
     * de errores generales del archivo.
     *
     * @return array{filas: array<int, array<string, mixed>>, errores: array<int, string>}
     */
    public function leer(string $contenido): array
    {
        $contenido = trim($this->aUtf8($contenido));

        if ($contenido === '') {
            return ['filas' => [], 'errores' => ['El archivo o el texto pegado está vacío.']];
        }

        if (str_contains($contenido, "\0")) {
            return ['filas' => [], 'errores' => ['El contenido no parece ser un archivo de texto CSV.']];
        }

        $delimitador = $this->detectarDelimitador($contenido);
        $registros = $this->parsearCsv($contenido, $delimitador);

        if ($registros === []) {
            return ['filas' => [], 'errores' => ['No se encontraron filas para importar.']];
        }

        $columnas = $this->detectarColumnas($registros);

        if ($columnas['faltantes'] !== []) {
            return [
                'filas' => [],
                'errores' => ['La plantilla no incluye las columnas: '.implode(', ', $columnas['faltantes']).'.'],
            ];
        }

        $filas = [];

        foreach (array_slice($registros, $columnas['desde']) as $registro) {
            $filas[] = $this->leerFila($registro, $columnas['mapa']);
        }

        if ($filas === []) {
            return ['filas' => [], 'errores' => ['No se encontraron productos para importar.']];
        }

        if (count($filas) > self::MAX_FILAS) {
            return [
                'filas' => [],
                'errores' => ['La importación admite hasta '.self::MAX_FILAS.' productos por vez.'],
            ];
        }

        $this->marcarDuplicados($filas);

        return ['filas' => $filas, 'errores' => []];
    }

    /**
     * Completa cada fila con el estado respecto de la base (nuevo, actualiza o inactivo).
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    public function analizar(array $filas): array
    {
        $existentes = $this->productosPorNombre();

        foreach ($filas as &$fila) {
            $producto = $existentes[$this->clave($fila['nombre'])] ?? null;

            if ($producto) {
                $fila['producto_id'] = $producto->id;
                $fila['estado'] = $producto->activo ? 'actualiza' : 'inactivo';
                $fila['nota'] = $producto->activo
                    ? 'Actualiza precios y stock mínimo. El stock no cambia.'
                    : 'Está inactivo: actualiza precios y mínimo, pero sigue inactivo.';
            } else {
                $fila['producto_id'] = null;
                $fila['estado'] = 'nuevo';
                $fila['nota'] = (int) $fila['stock_inicial'] > 0
                    ? 'Se crea con stock inicial y su movimiento de ingreso.'
                    : 'Se crea con stock 0.';
            }
        }

        unset($fila);

        return $filas;
    }

    /**
     * Indica si alguna fila tiene errores de validación.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function hayErrores(array $filas): bool
    {
        foreach ($filas as $fila) {
            if (($fila['errores'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda la importación completa en una sola transacción.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{creados: int, actualizados: int, movimientos: int}
     */
    public function confirmar(array $filas, User $user): array
    {
        return DB::transaction(function () use ($filas, $user) {
            // Defensa adicional: aunque el análisis ya validó, la sesión podría
            // haber quedado desactualizada. Un nombre repetido crearía dos productos.
            $vistos = [];

            foreach ($filas as $fila) {
                $clave = $this->clave($fila['nombre']);

                if (isset($vistos[$clave])) {
                    throw new \InvalidArgumentException('La importación tiene nombres repetidos.');
                }

                $vistos[$clave] = true;
            }

            // Se bloquean los productos existentes para que dos importaciones
            // simultáneas no creen el mismo producto dos veces.
            $existentes = $this->productosPorNombre(true);

            $nuevos = [];
            $actualizaciones = [];

            foreach ($filas as $fila) {
                $producto = $existentes[$this->clave($fila['nombre'])] ?? null;

                if ($producto) {
                    $actualizaciones[] = [
                        'id' => $producto->id,
                        'precio_costo' => $fila['precio_costo'],
                        'precio_venta' => $fila['precio_venta'],
                        'stock_minimo' => $fila['stock_minimo'],
                    ];
                } else {
                    $nuevos[] = $fila;
                }
            }

            $ahora = now();
            $movimientos = 0;

            if ($nuevos !== []) {
                $registros = array_map(fn (array $fila) => [
                    'nombre' => $fila['nombre'],
                    'precio_costo' => $fila['precio_costo'],
                    'precio_venta' => $fila['precio_venta'],
                    // El stock inicial se registra también como movimiento de ingreso,
                    // así el stock del producto coincide con la suma de sus movimientos.
                    'stock' => $fila['stock_inicial'],
                    'stock_minimo' => $fila['stock_minimo'],
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ], $nuevos);

                // Inserción en lote: una sola consulta para todos los productos nuevos.
                Producto::insert($registros);

                $creados = Producto::whereIn('nombre', array_column($registros, 'nombre'))
                    ->get()
                    ->keyBy(fn (Producto $producto) => $this->clave($producto->nombre));

                $filasMovimiento = [];

                foreach ($nuevos as $fila) {
                    $producto = $creados->get($this->clave($fila['nombre']));

                    // No debería ocurrir: el producto se acaba de insertar.
                    if (! $producto) {
                        continue;
                    }

                    $cantidad = (int) $fila['stock_inicial'];

                    if ($cantidad > 0) {
                        $filasMovimiento[] = [
                            'producto_id' => $producto->id,
                            'user_id' => $user->id,
                            'tipo' => MovimientoStock::TIPO_INGRESO,
                            'cantidad' => $cantidad,
                            'stock_anterior' => 0,
                            'stock_posterior' => $cantidad,
                            'observacion' => Str::limit('Importación de productos', 255),
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ];
                    }
                }

                if ($filasMovimiento !== []) {
                    // Inserción en lote de todos los movimientos de ingreso.
                    MovimientoStock::insert($filasMovimiento);
                    $movimientos = count($filasMovimiento);
                }
            }

            foreach ($actualizaciones as $datos) {
                Producto::whereKey($datos['id'])->update([
                    'precio_costo' => $datos['precio_costo'],
                    'precio_venta' => $datos['precio_venta'],
                    'stock_minimo' => $datos['stock_minimo'],
                    'updated_at' => $ahora,
                ]);
            }

            return [
                'creados' => count($nuevos),
                'actualizados' => count($actualizaciones),
                'movimientos' => $movimientos,
            ];
        });
    }

    /**
     * Convierte el contenido a UTF-8. Excel en español suele guardar CSV en
     * Windows-1252, por eso se detecta y se convierte cuando hace falta.
     */
    private function aUtf8(string $contenido): string
    {
        // BOM de UTF-8 que agrega Excel al guardar como "CSV UTF-8".
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        return $contenido;
    }

    /**
     * Detecta el separador mirando la primera línea: ";" (Excel en español),
     * "," o tabulación (texto pegado desde Excel).
     */
    private function detectarDelimitador(string $contenido): string
    {
        $primeraLinea = strtok($contenido, "\n") ?: $contenido;

        $conteos = [
            ';' => substr_count($primeraLinea, ';'),
            ',' => substr_count($primeraLinea, ','),
            "\t" => substr_count($primeraLinea, "\t"),
        ];

        arsort($conteos);
        $delimitador = array_key_first($conteos);

        return $conteos[$delimitador] > 0 ? $delimitador : ';';
    }

    /**
     * Parsea el CSV respetando comillas y saltos de línea dentro de campos.
     *
     * @return array<int, array{linea: int, celdas: array<int, string>}>
     */
    private function parsearCsv(string $contenido, string $delimitador): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $contenido);
        rewind($handle);

        $registros = [];
        $numero = 0;

        while (($celdas = fgetcsv($handle, 0, $delimitador, '"', '')) !== false) {
            $numero++;
            $celdas = array_map(fn ($celda) => trim((string) $celda), $celdas);

            // Se descartan las celdas vacías del final (Excel agrega separadores de más).
            while ($celdas !== [] && end($celdas) === '') {
                array_pop($celdas);
            }

            if ($celdas === []) {
                continue;
            }

            $registros[] = ['linea' => $numero, 'celdas' => array_values($celdas)];
        }

        fclose($handle);

        return $registros;
    }

    /**
     * Busca una fila de encabezado en las primeras filas (permite títulos arriba de la tabla)
     * y devuelve en qué posición está cada columna. Si no hay encabezado reconocible,
     * se asume que las filas respetan el orden de la plantilla.
     *
     * @param  array<int, array{linea: int, celdas: array<int, string>}>  $registros
     * @return array{mapa: array<string, int>, desde: int, faltantes: array<int, string>}
     */
    private function detectarColumnas(array $registros): array
    {
        // Se revisan las primeras filas para tolerar una fila de título antes del encabezado.
        $limite = min(count($registros), 5);

        for ($indiceFila = 0; $indiceFila < $limite; $indiceFila++) {
            $celdas = array_map(
                fn (string $celda) => $this->normalizarClave($celda),
                $registros[$indiceFila]['celdas']
            );

            $mapa = [];

            foreach ($celdas as $indice => $celda) {
                if ($celda === '') {
                    continue;
                }

                foreach (self::ALIAS as $columna => $alias) {
                    if (! array_key_exists($columna, $mapa) && in_array($celda, $alias, true)) {
                        $mapa[$columna] = $indice;
                        break;
                    }
                }
            }

            // Se considera encabezado si reconoce al menos dos columnas e incluye "nombre".
            if (count($mapa) >= 2 && array_key_exists('nombre', $mapa)) {
                return [
                    'mapa' => $mapa,
                    'desde' => $indiceFila + 1,
                    'faltantes' => array_values(array_diff(self::OBLIGATORIAS, array_keys($mapa))),
                ];
            }
        }

        // Sin encabezado reconocible se asume el orden de la plantilla.
        return [
            'mapa' => array_combine(self::COLUMNAS, range(0, count(self::COLUMNAS) - 1)),
            'desde' => 0,
            'faltantes' => [],
        ];
    }

    /**
     * Convierte una fila del CSV en una fila normalizada con sus errores.
     *
     * @param  array{linea: int, celdas: array<int, string>}  $registro
     * @param  array<string, int>  $mapa
     * @return array<string, mixed>
     */
    private function leerFila(array $registro, array $mapa): array
    {
        $valor = fn (string $columna): string => isset($mapa[$columna])
            ? (string) ($registro['celdas'][$mapa[$columna]] ?? '')
            : '';

        $errores = [];
        $decimales = configuracion()->decimales();

        $nombre = trim($valor('nombre'));

        if ($nombre === '') {
            $errores['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($nombre) > 255) {
            $errores['nombre'] = 'El nombre no puede superar los 255 caracteres.';
        }

        [$precioCosto, $errorCosto] = $this->leerMonto($valor('precio_costo'), 'El precio de costo', $decimales, true);
        [$precioVenta, $errorVenta] = $this->leerMonto($valor('precio_venta'), 'El precio de venta', $decimales, true);

        if ($errorCosto) {
            $errores['precio_costo'] = $errorCosto;
        }

        if ($errorVenta) {
            $errores['precio_venta'] = $errorVenta;
        }

        $stockMinimo = $this->parsearEntero($valor('stock_minimo'));

        if (trim($valor('stock_minimo')) === '') {
            $errores['stock_minimo'] = 'El stock mínimo es obligatorio.';
        } elseif ($stockMinimo === null) {
            $errores['stock_minimo'] = 'El stock mínimo debe ser un número entero.';
        } elseif ($stockMinimo < 0) {
            $errores['stock_minimo'] = 'El stock mínimo no puede ser negativo.';
        }

        $textoInicial = trim($valor('stock_inicial'));
        $stockInicial = $textoInicial === '' ? 0 : $this->parsearEntero($textoInicial);

        if ($stockInicial === null) {
            $errores['stock_inicial'] = 'El stock inicial debe ser un número entero.';
        } elseif ($stockInicial < 0) {
            $errores['stock_inicial'] = 'El stock inicial no puede ser negativo.';
        }

        return [
            'linea' => $registro['linea'],
            'nombre' => $nombre,
            'precio_costo' => $precioCosto ?? 0.0,
            'precio_venta' => $precioVenta ?? 0.0,
            'stock_minimo' => max(0, (int) $stockMinimo),
            'stock_inicial' => max(0, (int) $stockInicial),
            'errores' => $errores,
        ];
    }

    /**
     * Lee un monto con coma o punto decimal, según la configuración del local.
     *
     * @return array{0: ?float, 1: ?string} [valor, mensaje de error]
     */
    private function leerMonto(string $texto, string $etiqueta, int $decimales, bool $obligatorio): array
    {
        if (trim($texto) === '') {
            return [null, $obligatorio ? "{$etiqueta} es obligatorio." : null];
        }

        $monto = $this->parsearMonto($texto);

        if ($monto === null) {
            return [null, "{$etiqueta} no es un número válido."];
        }

        if ($monto['valor'] < 0) {
            return [null, "{$etiqueta} no puede ser negativo."];
        }

        if ($monto['decimales'] > $decimales) {
            return [null, "{$etiqueta} tiene más decimales de los que permite la configuración del local."];
        }

        if ($monto['valor'] > 9999999999.99) {
            return [null, "{$etiqueta} es demasiado grande."];
        }

        return [$monto['valor'], null];
    }

    /**
     * Normaliza un monto escrito por una persona o exportado por Excel.
     *
     * Acepta "1.234,56", "1234,56", "1,234.56", "1234.56", "$ 1.234,56" y
     * también enteros con separador de miles ("1.234").
     *
     * @return array{valor: float, decimales: int}|null
     */
    private function parsearMonto(string $texto): ?array
    {
        $limpio = preg_replace('/[^\d.,+\-]/u', '', trim($texto));

        if ($limpio === null || $limpio === '' || $limpio === '-' || $limpio === '+') {
            return null;
        }

        $negativo = str_contains($limpio, '-');
        $limpio = str_replace(['-', '+'], '', $limpio);

        if (! preg_match('/^\d+(?:[.,]\d+)*$/', $limpio)) {
            return null;
        }

        $parteEntera = $limpio;
        $parteDecimal = '';

        $tieneComa = str_contains($limpio, ',');
        $tienePunto = str_contains($limpio, '.');

        if ($tieneComa && $tienePunto) {
            // El último separador es el decimal; el otro es de miles.
            $posicionComa = strrpos($limpio, ',');
            $posicionPunto = strrpos($limpio, '.');
            $posicion = max($posicionComa, $posicionPunto);
            $separador = $limpio[$posicion];

            $parteEntera = substr($limpio, 0, $posicion);
            $parteDecimal = substr($limpio, $posicion + 1);
            $parteEntera = str_replace($separador === ',' ? '.' : ',', '', $parteEntera);
        } elseif ($tieneComa) {
            if (substr_count($limpio, ',') > 1) {
                // "1,234,567": comas de miles.
                $parteEntera = str_replace(',', '', $limpio);
            } else {
                $posicion = strrpos($limpio, ',');
                $parteEntera = substr($limpio, 0, $posicion);
                $parteDecimal = substr($limpio, $posicion + 1);
            }
        } elseif ($tienePunto) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $limpio)) {
                // "1.234" o "1.234.567": puntos de miles.
                $parteEntera = str_replace('.', '', $limpio);
            } else {
                $posicion = strrpos($limpio, '.');
                $parteEntera = substr($limpio, 0, $posicion);
                $parteDecimal = substr($limpio, $posicion + 1);
            }
        }

        $parteEntera = $parteEntera === '' ? '0' : $parteEntera;

        if (! ctype_digit($parteEntera) || ($parteDecimal !== '' && ! ctype_digit($parteDecimal))) {
            return null;
        }

        $valor = (float) ($parteEntera.'.'.($parteDecimal === '' ? '0' : $parteDecimal));

        return [
            'valor' => $negativo ? -$valor : $valor,
            'decimales' => strlen($parteDecimal),
        ];
    }

    /**
     * Convierte un texto en entero. Acepta separadores de miles con punto.
     */
    private function parsearEntero(string $texto): ?int
    {
        $limpio = str_replace(['+', ' '], '', trim($texto));
        $negativo = str_starts_with($limpio, '-');

        if ($negativo) {
            $limpio = substr($limpio, 1);
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $limpio)) {
            $limpio = str_replace('.', '', $limpio);
        }

        if ($limpio === '' || ! ctype_digit($limpio)) {
            return null;
        }

        $valor = (int) $limpio;

        return $negativo ? -$valor : $valor;
    }

    /**
     * Marca los nombres repetidos dentro del mismo archivo (el segundo en adelante).
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function marcarDuplicados(array &$filas): void
    {
        $vistos = [];

        foreach ($filas as &$fila) {
            $clave = $this->clave($fila['nombre']);

            if ($clave === '') {
                continue;
            }

            if (isset($vistos[$clave])) {
                $fila['errores']['nombre'] = "El producto ya aparece en la fila {$vistos[$clave]}.";
            } else {
                $vistos[$clave] = $fila['linea'];
            }
        }

        unset($fila);
    }

    /**
     * Productos indexados por nombre normalizado (sin mayúsculas ni acentos).
     *
     * @return array<string, Producto>
     */
    private function productosPorNombre(bool $bloquear = false): array
    {
        $consulta = Producto::query()->select(['id', 'nombre', 'precio_costo', 'precio_venta', 'stock_minimo', 'activo']);

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->get()
            ->keyBy(fn (Producto $producto) => $this->clave($producto->nombre))
            ->all();
    }

    /**
     * Clave de comparación de nombres: minúsculas, sin acentos y con espacios simples.
     */
    private function clave(string $nombre): string
    {
        return $this->normalizarClave($nombre);
    }

    /**
     * Normaliza un texto para comparar encabezados y nombres de productos.
     */
    private function normalizarClave(string $texto): string
    {
        $texto = Str::ascii(mb_strtolower(trim($texto)));
        $texto = preg_replace('/[^a-z0-9]+/u', '_', $texto) ?? '';

        return trim($texto, '_');
    }
}
