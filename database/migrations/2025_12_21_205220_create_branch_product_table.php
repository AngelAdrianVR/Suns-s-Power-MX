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
        Schema::create('branch_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Datos específicos por sucursal
            $table->float('current_stock')->unsigned()->default(0);
            $table->float('min_stock_alert')->unsigned()->default(1);
            $table->string('location_in_warehouse')->nullable(); // Ej: "Pasillo 3, Estante B"
            
            $table->timestamps();
            
            // Evitar duplicados: un producto solo aparece una vez por sucursal
            $table->unique(['branch_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_product');
    }
};
