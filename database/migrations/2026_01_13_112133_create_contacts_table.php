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
        // 1. Crear la tabla polimórfica de contactos
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            
            // Multi-tenancy: Un contacto debe pertenecer a una sucursal para evitar fugas de información
            $table->foreignId('branch_id')->default(1)->constrained()->onDelete('cascade');

            // Campos polimórficos: contactable_id y contactable_type
            // Esto permite asociar el contacto a Supplier, Client, User, etc.
            $table->morphs('contactable');

            $table->string('name'); // Nombre de la persona de contacto
            $table->string('job_title')->nullable(); // Puesto (Ej: Gerente de Ventas)
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Flag para saber cuál es el contacto principal en caso de tener varios
            $table->boolean('is_primary')->default(false);
            
            $table->text('notes')->nullable(); // Notas adicionales sobre el contacto

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
