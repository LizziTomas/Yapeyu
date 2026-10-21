<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Descuento en porcentaje (0 a 100) aplicado a cada renglón de la venta.
     * El subtotal del renglón se guarda con el descuento ya aplicado.
     */
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->unsignedTinyInteger('descuento_porcentaje')->default(0)->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('descuento_porcentaje');
        });
    }
};
