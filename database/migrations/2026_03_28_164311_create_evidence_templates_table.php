<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Plantillas de evidencias configuradas en ajustes
        Schema::create('evidence_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('system_type'); // Interconectado, Autónomo, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Evidencias requeridas copiadas a la orden de servicio
        Schema::create('service_order_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('comment')->nullable(); // Para que el técnico pueda agregar comentarios específicos al subir la evidencia
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_order_evidences');
        Schema::dropIfExists('evidence_templates');
    }
};