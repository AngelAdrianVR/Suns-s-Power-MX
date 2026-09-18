<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasos configurables de la documentación de servicio (cada paso = un documento)
     * y los archivos adjuntos/vinculados por paso para cada orden de servicio.
     */
    public function up(): void
    {
        Schema::create('service_documentation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_documentation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            // Si el paso se elimina, el archivo queda como huérfano (se puede limpiar manualmente)
            $table->foreignId('step_id')->nullable()->constrained('service_documentation_steps')->nullOnDelete();
            // upload (subido en el asistente) | client (vinculado del expediente del cliente) | order (vinculado de la orden)
            $table->string('source');
            // id del registro en la tabla media (para uploads y referencia de vínculos)
            $table->unsignedBigInteger('media_id')->nullable();
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_documentation_attachments');
        Schema::dropIfExists('service_documentation_steps');
    }
};
