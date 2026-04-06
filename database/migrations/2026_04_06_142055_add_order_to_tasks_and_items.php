<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('priority');
        });
        
        Schema::table('service_order_items', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('quantity');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('order');
        });
        
        Schema::table('service_order_items', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};