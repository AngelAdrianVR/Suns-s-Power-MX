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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->foreignId('service_order_id')->constrained()->onDelete('cascade');
            $table->dateTime('generated_at'); // Fecha y hora de generación del contrato
            $table->json('content_json')->nullable(); // Cláusulas dinámicas
            $table->string('signed_url')->nullable(); // URL del contrato firmado
            $table->enum('status', ['Borrador', 'Enviado', 'Firmado'])->default('Borrador');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
