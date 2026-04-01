<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('system_type'); // Interconectado, Autónomo, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['Baja', 'Media', 'Alta'])->default('Media');
            $table->timestamps();
        });

        // Tabla pivote para los usuarios asignados por defecto a la tarea
        Schema::create('task_template_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained('task_templates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['task_template_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_template_user');
        Schema::dropIfExists('task_templates');
    }
};