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
        // 1. Agregar los campos de recurrencia a la tabla "tasks"
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('due_date');
            $table->integer('recurring_interval')->nullable()->default(1)->after('is_recurring');
            $table->string('recurring_unit')->nullable()->default('months')->after('recurring_interval');
        });

        // 2. Crear la tabla pivote para relacionar Tareas con Evidencias de la Orden
        Schema::create('service_order_evidence_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('service_order_evidence_id')->constrained('service_order_evidences')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El método down revierte los cambios por si necesitas hacer un rollback
        Schema::dropIfExists('service_order_evidence_task');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['is_recurring', 'recurring_interval', 'recurring_unit']);
        });
    }
};