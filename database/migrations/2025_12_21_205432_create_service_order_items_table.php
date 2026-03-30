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
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->integer('quantity');
            // Agregamos la cantidad realmente usada. Nullable porque al inicio no se sabe.
            $table->decimal('used_quantity', 10, 2)->nullable(); // Cantidad utilizada en el servicio
            $table->decimal('price', 10, 2); // Precio de venta al momento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_items');
    }
};
