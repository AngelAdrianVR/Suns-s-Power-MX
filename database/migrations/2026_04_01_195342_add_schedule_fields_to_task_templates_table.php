<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Cambiamos a la tabla task_templates
        Schema::table('task_templates', function (Blueprint $table) {
            $table->integer('start_days')->default(0);
            $table->integer('duration_days')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Revertimos las columnas correctas en la tabla correcta
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn(['start_days', 'duration_days']);
        });
    }
};