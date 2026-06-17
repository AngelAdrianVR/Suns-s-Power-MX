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
            // Precio de mantenimiento por módulo (escalable para futuras adiciones)
            $table->decimal('price_per_module', 10, 2)->nullable()->after('down_payment')
                  ->comment('Precio de mantenimiento por módulo en MXN. Escalable por servicio.');

            // Campo JSON para datos extra escalables por servicio en el futuro
            $table->json('extra_data')->nullable()->after('price_per_module')
                  ->comment('Datos adicionales escalables específicos de cada servicio (ej: configuraciones, metadatos).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['price_per_module', 'extra_data']);
        });
    }
};
