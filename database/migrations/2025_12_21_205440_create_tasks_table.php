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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            // Relación con la Orden de Servicio (Puede ser null si es una tarea administrativa general)
            $table->foreignId('service_order_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            
            // Relaciones
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // (Quién lo asignó)
            // Fecha de inicio para poder hacer rangos (Gantt)
            $table->dateTime('start_date')->nullable(); // Fecha de inicio para poder hacer rangos (Gantt)
            $table->dateTime('finish_date')->nullable(); // Fecha de finalización real
            $table->dateTime('due_date')->nullable(); // Fecha límite
            
            $table->enum('status', ['Pendiente', 'En Proceso', 'Completado', 'Detenido'])->default('Pendiente');
            $table->enum('priority', ['Baja', 'Media', 'Alta'])->default('Media');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
