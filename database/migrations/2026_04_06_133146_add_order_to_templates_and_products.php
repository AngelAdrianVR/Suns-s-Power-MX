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
        Schema::table('task_templates', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('priority');
        });
        Schema::table('product_system_type', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates_and_products', function (Blueprint $table) {
            //
        });
    }
};
