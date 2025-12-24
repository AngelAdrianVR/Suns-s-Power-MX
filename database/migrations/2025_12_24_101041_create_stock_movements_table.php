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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Tenant: El historial pertenece a una sucursal específica
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Producto afectado
            $table->foreignId('product_id')->constrained()->onDelete('cascade');

            // Auditoría: Quién hizo el movimiento (puede ser null si es proceso automático, pero idealmente siempre un usuario)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // Tipo de movimiento para filtrados rápidos
            // Entrada: Compra, Devolución
            // Salida: Venta, Instalación, Merma
            // Ajuste: Inventario físico (Corrección)
            $table->enum('type', ['Entrada', 'Salida', 'Ajuste']);

            // Cantidad movida (siempre positiva, el type define si suma o resta)
            // Usamos decimal para soportar metros de cable, etc.
            $table->decimal('quantity', 10, 2);

            // Snapshot: Stock resultante DESPUÉS del movimiento (vital para auditorías sin recalcular todo)
            $table->decimal('stock_after', 10, 2);

            // Polimorfismo: ¿Qué originó esto?
            // Order #100, ServiceOrder #500, o null si fue ajuste manual directo
            $table->nullableMorphs('reference');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices para optimizar la consulta del "Show Product"
            $table->index(['branch_id', 'product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
