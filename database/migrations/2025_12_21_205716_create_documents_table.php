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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->string('name');
            // $table->string('file_path'); // se administrará con media library de spatie
            $table->string('mime_type')->nullable(); // pdf, jpg, png
            
            // Campos polimórficos: documentable_id y documentable_type
            $table->morphs('documentable'); 
            
            $table->enum('category', ['Plano', 'Contrato', 'Evidencia', 'Factura', 'Manual']);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
