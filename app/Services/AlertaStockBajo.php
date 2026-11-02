<?php

namespace App\Services;

use App\Mail\StockBajoMail;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía los avisos de stock bajo por correo a todos los administradores.
 *
 * El envío nunca interrumpe la operación que lo originó: cualquier error de
 * SMTP se registra en el log y la venta o el descarte se completan igual.
 */
class AlertaStockBajo
{
    /**
     * Dominios de prueba que no existen en internet: mandarles un mail
     * sólo genera rebotes, así que se descartan.
     *
     * @var array<int, string>
     */
    private const DOMINIOS_IGNORADOS = [
        'local.test',
        'example.com',
        'example.org',
        'localhost',
        'test',
    ];

    /**
     * Avisa cuando una operación dejó uno o más productos en su stock mínimo o por debajo.
     *
     * Sólo se notifica el cruce: si el producto ya estaba bajo el mínimo antes de
     * la operación, no se vuelve a avisar (evita un mail por cada venta).
     *
     * @param  array<int, array{nombre: string, stock: int, stock_minimo: int, stock_anterior: int}>  $cambios
     */
    public function porOperacion(array $cambios, string $motivo, ?string $usuario = null): void
    {
        $alertas = $this->soloCruces($cambios);

        if ($alertas === []) {
            return;
        }

        $this->enviar($alertas, $motivo, $usuario);
    }

    /**
     * Envía el resumen periódico con todos los productos activos bajo el mínimo.
     *
     * @param  iterable<int, Producto>  $productos
     * @return bool  true si el mail se envió correctamente.
     */
    public function resumen(iterable $productos, string $motivo = 'Resumen periódico de stock bajo'): bool
    {
        $alertas = [];

        foreach ($productos as $producto) {
            if ($producto->isStockBajo()) {
                $alertas[] = $this->fila($producto);
            }
        }

        if ($alertas === []) {
            return false;
        }

        return $this->enviar($alertas, $motivo);
    }

    /**
     * Deja únicamente los productos que pasaron de estar por encima del mínimo
     * a estar en el mínimo o por debajo.
     *
     * @param  array<int, array{nombre: string, stock: int, stock_minimo: int, stock_anterior: int}>  $cambios
     * @return array<int, array{nombre: string, stock: int, stock_minimo: int}>
     */
    private function soloCruces(array $cambios): array
    {
        $alertas = [];

        foreach ($cambios as $cambio) {
            $cruzó = $cambio['stock_anterior'] > $cambio['stock_minimo']
                && $cambio['stock'] <= $cambio['stock_minimo'];

            if ($cruzó) {
                $alertas[] = [
                    'nombre' => $cambio['nombre'],
                    'stock' => (int) $cambio['stock'],
                    'stock_minimo' => (int) $cambio['stock_minimo'],
                ];
            }
        }

        return $alertas;
    }

    /**
     * Arma el renglón del mail a partir del producto.
     *
     * @return array{nombre: string, stock: int, stock_minimo: int}
     */
    private function fila(Producto $producto): array
    {
        return [
            'nombre' => $producto->nombre,
            'stock' => (int) $producto->stock,
            'stock_minimo' => (int) $producto->stock_minimo,
        ];
    }

    /**
     * Envía el mail a los administradores y protege la operación de origen.
     *
     * @param  array<int, array{nombre: string, stock: int, stock_minimo: int}>  $alertas
     * @return bool  true si el mail salió sin errores.
     */
    private function enviar(array $alertas, string $motivo, ?string $usuario = null): bool
    {
        $destinatarios = $this->destinatarios();

        if ($destinatarios === []) {
            Log::warning('Alerta de stock bajo sin destinatarios válidos.', [
                'motivo' => $motivo,
                'productos' => array_column($alertas, 'nombre'),
            ]);

            return false;
        }

        try {
            Mail::to($destinatarios)->send(new StockBajoMail($alertas, $motivo, $usuario));

            return true;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la alerta de stock bajo.', [
                'motivo' => $motivo,
                'productos' => array_column($alertas, 'nombre'),
                'destinatarios' => $destinatarios,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mails de todos los usuarios con rol administrador, sin duplicados y sin
     * direcciones de prueba que rebotarían.
     *
     * @return array<int, string>
     */
    private function destinatarios(): array
    {
        return User::role('admin')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $this->esDestinatarioValido($email))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Indica si el mail es una dirección real a la que se pueda escribir.
     */
    private function esDestinatarioValido(string $email): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $dominio = substr(strrchr($email, '@') ?: '', 1);

        if (in_array($dominio, self::DOMINIOS_IGNORADOS, true)) {
            return false;
        }

        return ! str_ends_with($dominio, '.test') && ! str_ends_with($dominio, '.local');
    }
}
