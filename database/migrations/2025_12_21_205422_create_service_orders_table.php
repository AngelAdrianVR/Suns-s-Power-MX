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
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->foreignId('client_id')->constrained()->onDelete('restrict');
            $table->foreignId('technician_id')->nullable()->constrained('users'); 
            $table->foreignId('sales_rep_id')->constrained('users'); 
            
            $table->enum('status', [
                'Cotización', 'Aceptado', 'En Proceso', 'Completado', 'Facturado', 'Cancelado'
            ])->default('Cotización');
            
            $table->dateTime('start_date')->nullable();
            $table->string('service_number')->nullable(); // Número de servicio
            $table->string('rate_type')->nullable();      // Tipo de tarifa
            $table->string('system_type')->nullable();    // Tipo de Sistema (Interconectado, Autónomo, etc.)
            $table->string('meter_number')->nullable();   // Número de medidor
            $table->dateTime('completion_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0); 
            
            // Dirección de Instalación (Específica de la orden)
            // Usamos prefijos para distinguirla de la dirección fiscal del cliente
            $table->string('installation_street')->nullable(); // Calle
            $table->string('installation_exterior_number')->nullable(); // Número Exterior
            $table->string('installation_interior_number')->nullable(); // Número Interior
            $table->string('installation_neighborhood')->nullable(); // Colonia
            $table->string('installation_municipality')->nullable(); // Municipio / Alcaldía
            $table->string('installation_state')->nullable();   // Estado
            $table->string('installation_zip_code')->nullable(); // Código Postal
            $table->string('installation_country')->default('México');  // País

            $table->text('notes')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
