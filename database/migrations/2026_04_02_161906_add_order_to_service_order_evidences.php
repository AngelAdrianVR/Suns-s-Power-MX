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
        Schema::table('service_order_evidences', function (Blueprint $table) {
            // Aquí agregamos la nueva columna
            $table->integer('order')->default(0)->after('allows_multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_order_evidences', function (Blueprint $table) {
            // Aquí eliminamos la columna por si hacemos un rollback
            $table->dropColumn('order');
        });
    }
};