<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockBajoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Aviso de productos que quedaron con stock mínimo o inferior.
     *
     * @param  array<int, array{nombre: string, stock: int, stock_minimo: int}>  $productos
     * @param  string  $motivo  Operación que originó el aviso (o el resumen periódico).
     * @param  string|null  $usuario  Usuario responsable de la operación, si corresponde.
     */
    public function __construct(
        public array $productos,
        public string $motivo,
        public ?string $usuario = null,
    ) {
    }

    /**
     * Asunto del mail: nombra el producto cuando es uno solo y la cantidad cuando son varios.
     */
    public function envelope(): Envelope
    {
        $cantidad = count($this->productos);

        $detalle = $cantidad === 1
            ? $this->productos[0]['nombre']
            : "{$cantidad} productos";

        $local = configuracion()->nombre_local;

        return new Envelope(
            from: new Address(config('mail.from.address'), $local),
            subject: "[{$local}] Stock bajo: {$detalle}",
        );
    }

    /**
     * Contenido del mail (vista Blade con estilos en línea para clientes de correo).
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.stock-bajo',
            with: [
                'productos' => $this->productos,
                'motivo' => $this->motivo,
                'usuario' => $this->usuario,
                'fecha' => now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i'),
            ],
        );
    }
}
