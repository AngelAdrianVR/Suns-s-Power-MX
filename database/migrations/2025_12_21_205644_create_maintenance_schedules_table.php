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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('service_order_id')->constrained(); // Instalación original
            $table->foreignId('technician_id')->nullable()->constrained('users');
            
            $table->enum('frequency', ['Mensual', 'Trimestral', 'Semestral', 'Anual']);
            $table->date('scheduled_date');
            $table->enum('status', ['Programado', 'Notificado', 'Realizado', 'Vencido'])->default('Programado');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
