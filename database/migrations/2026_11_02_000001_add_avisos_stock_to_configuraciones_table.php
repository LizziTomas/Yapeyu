<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la configuración de los avisos de stock bajo:
     * si están activos, cada cuántos días se manda el resumen y cuándo se
     * envió por última vez (para no repetirlo antes de tiempo).
     */
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->boolean('avisos_stock')->default(true)->after('usa_centavos');
            $table->unsignedTinyInteger('resumen_stock_dias')->default(3)->after('avisos_stock');
            $table->date('resumen_stock_ultimo_envio')->nullable()->after('resumen_stock_dias');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn(['avisos_stock', 'resumen_stock_dias', 'resumen_stock_ultimo_envio']);
        });
    }
};
