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
            $table->string('title');
            $table->text('description')->nullable();
            
            // Relaciones
            $table->foreignId('assigned_to')->constrained('users')->onDelete('cascade'); // (Quién debe hacerlo)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // (Quién lo asignó)
            
            $table->dateTime('due_date')->nullable();
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
