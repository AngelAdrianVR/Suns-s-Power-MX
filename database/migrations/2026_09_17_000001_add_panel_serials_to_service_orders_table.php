<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            // Números de serie de los paneles solares instalados (uno por unidad).
            // El índice del arreglo corresponde a la unidad 1..N.
            $table->json('panel_serials')->nullable()->after('number_of_units')
                  ->comment('Números de serie de cada panel solar instalado, alineados por índice con la cantidad de unidades.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('panel_serials');
        });
    }
};
