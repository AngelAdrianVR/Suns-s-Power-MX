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
            // Datos de microinversores del diagrama unifilar. Cada entrada corresponde
            // a una rama (1 microinversor por cada 4 paneles):
            // [{ model: string, serial: string }, ...]
            $table->json('microinverters')->nullable()->after('panel_serials')
                  ->comment('Datos editables de los microinversores del diagrama unifilar (modelo y serie por rama).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('microinverters');
        });
    }
};
