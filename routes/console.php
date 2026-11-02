<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resumen periódico de stock bajo. Se evalúa todos los días a las 8 de la
// mañana (hora de Buenos Aires) y el comando decide si ya pasaron los días
// configurados en la pantalla de configuración del local.
Schedule::command('stock:resumen')
    ->dailyAt('08:00')
    ->timezone('America/Argentina/Buenos_Aires')
    ->withoutOverlapping();
