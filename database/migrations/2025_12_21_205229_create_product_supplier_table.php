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
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            
            // Datos específicos de la relación comercial
            $table->string('supplier_sku')->nullable(); // El código que usa el proveedor para este producto
            $table->decimal('purchase_price', 10, 2); // El precio que nos da ESTE proveedor
            $table->string('currency')->default('MXN'); // MXN o USD
            $table->integer('delivery_days')->default(1); // Tiempo de entrega estimado
            $table->boolean('is_preferred')->default(false); // Para marcar al proveedor favorito por defecto
            
            $table->timestamps();

            // Un producto solo debe aparecer una vez por proveedor en esta lista
            $table->unique(['product_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
