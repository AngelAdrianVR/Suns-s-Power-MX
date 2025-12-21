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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_service_order_id')->nullable()->constrained('service_orders');
            
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['Abierto', 'En Análisis', 'Resuelto', 'Cerrado'])->default('Abierto');
            $table->enum('priority', ['Baja', 'Media', 'Alta', 'Urgente'])->default('Media');
            
            $table->text('resolution_notes')->nullable();
            
            // Escalamiento: Si se convierte en una nueva orden (reparación costosa)
            // Usamos unsignedBigInteger porque la FK es sobre la misma tabla service_orders que ya existe
            $table->unsignedBigInteger('converted_to_order_id')->nullable(); 
            $table->foreign('converted_to_order_id')->references('id')->on('service_orders');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
