<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Services\AlertaStockBajo;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Envía el resumen periódico de productos con stock bajo a los administradores.
 *
 * Se ejecuta a diario y es el propio comando el que decide si ya pasaron los
 * días configurados, así la frecuencia se cambia desde la pantalla de
 * configuración y no hay que tocar el cron.
 */
class ResumenStockBajoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stock:resumen
                            {--force : Envía el resumen sin respetar la frecuencia configurada}';

    /**
     * @var string
     */
    protected $description = 'Envía por mail el resumen de productos con stock bajo';

    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    public function handle(AlertaStockBajo $alertas): int
    {
        $configuracion = Configuracion::actual();

        if (! $configuracion->avisos_stock) {
            $this->info('Los avisos de stock están desactivados en la configuración del local.');

            return self::SUCCESS;
        }

        if (! $this->debeEnviarse($configuracion)) {
            $dias = (int) $configuracion->resumen_stock_dias;
            $ultimo = $configuracion->resumen_stock_ultimo_envio?->format('d/m/Y') ?? 'nunca';

            $this->info("Todavía no corresponde enviar el resumen (cada {$dias} días, último envío: {$ultimo}).");

            return self::SUCCESS;
        }

        $productos = Producto::activos()
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->orderBy('stock')
            ->orderBy('nombre')
            ->get();

        if ($productos->isEmpty()) {
            $this->info('No hay productos con stock bajo: no se envía el resumen.');

            return self::SUCCESS;
        }

        if (! $alertas->resumen($productos->all())) {
            $this->error('No se pudo enviar el resumen. Revisá el log o la configuración de correo.');

            return self::FAILURE;
        }

        $configuracion->update(['resumen_stock_ultimo_envio' => $this->hoy()]);

        $this->info("Resumen enviado: {$productos->count()} producto(s) con stock bajo.");

        return self::SUCCESS;
    }

    /**
     * Indica si ya pasaron los días configurados desde el último envío.
     */
    private function debeEnviarse(Configuracion $configuracion): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $configuracion->resumen_stock_ultimo_envio) {
            return true;
        }

        $dias = max(1, (int) $configuracion->resumen_stock_dias);
        $proximoEnvio = $configuracion->resumen_stock_ultimo_envio->copy()->addDays($dias);

        return $proximoEnvio->toDateString() <= $this->hoy()->toDateString();
    }

    /**
     * Fecha actual en la zona horaria del local.
     */
    private function hoy(): Carbon
    {
        return Carbon::now(self::ZONA_HORARIA);
    }
}
