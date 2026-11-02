<?php

namespace App\Console\Commands;

use App\Mail\StockBajoMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Verifica en un paso que la configuración de correo funcione.
 *
 * Se ejecuta con: php artisan stock:probar-mail --to=alguien@dominio.com
 */
class ProbarMailCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stock:probar-mail
                            {--to= : Dirección de destino (por defecto, el remitente configurado)}';

    /**
     * @var string
     */
    protected $description = 'Envía un mail de prueba con el aviso de stock bajo para validar el SMTP';

    public function handle(): int
    {
        $destino = $this->option('to') ?: config('mail.from.address');
        $remitente = config('mail.from.address');

        if (! $destino) {
            $this->error('No hay dirección de destino. Usá --to=alguien@dominio.com');

            return self::FAILURE;
        }

        $this->line("Enviando mail de prueba a {$destino} desde {$remitente}...");

        try {
            Mail::to($destino)->send(new StockBajoMail(
                [['nombre' => 'Producto de prueba', 'stock' => 1, 'stock_minimo' => 5]],
                'Prueba de configuración de correo'
            ));
        } catch (Throwable $e) {
            $this->error('No se pudo enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Mail enviado correctamente. Revisá la bandeja de entrada (y el spam).');

        return self::SUCCESS;
    }
}
