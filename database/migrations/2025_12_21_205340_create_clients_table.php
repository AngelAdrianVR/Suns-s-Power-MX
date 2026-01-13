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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            
            // Datos Identificación
            $table->string('name'); // Razón social o Nombre completo
            $table->string('contact_person')->nullable();
            $table->string('tax_id')->nullable(); // RFC
            
            // Datos de Contacto
            $table->string('email')->nullable();
            $table->string('email_secondary')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();

            // Dirección Desglosada (Address Splitting)
            $table->string('street')->nullable();           // Calle
            $table->string('exterior_number')->nullable();  // Número Exterior
            $table->string('interior_number')->nullable();  // Número Interior
            $table->string('neighborhood')->nullable();     // Colonia
            $table->string('municipality')->nullable();     // Municipio / Alcaldía
            $table->string('state')->nullable();            // Estado
            $table->string('zip_code')->nullable();         // Código Postal
            $table->string('country')->default('México');   // País
            
            $table->string('coordinates')->nullable(); // Lat,Lng
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
