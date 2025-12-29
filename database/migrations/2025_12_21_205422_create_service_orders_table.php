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
            $table->foreignId('technician_id')->nullable()->constrained('users'); // Puede asignarse después
            $table->foreignId('sales_rep_id')->constrained('users'); // Representante de ventas que creó la orden
            
            $table->enum('status', [
                'Cotización', 'Aceptado', 'En Proceso', 'Completado', 'Facturado', 'Cancelado'
            ])->default('Cotización');
            
            $table->dateTime('start_date')->nullable(); // Fecha de inicio del servicio
            $table->dateTime('completion_date')->nullable(); // Fecha de finalización del servicio
            $table->decimal('total_amount', 12, 2)->default(0); // Monto total del servicio
            $table->text('installation_address')->nullable(); // Dirección de instalación
            $table->text('notes')->nullable(); // Notas adicionales
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
