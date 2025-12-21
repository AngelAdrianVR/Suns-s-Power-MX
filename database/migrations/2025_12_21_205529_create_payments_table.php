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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->foreignId('client_id')->constrained()->onDelete('restrict');
            // Relación opcional con orden de servicio (puede ser un abono a saldo general)
            $table->foreignId('service_order_id')->nullable()->constrained()->onDelete('restrict');
            
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('method', ['Transferencia', 'Efectivo', 'Cheque', 'Tarjeta']);
            $table->string('reference')->nullable(); // Número de referencia o cheque
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
