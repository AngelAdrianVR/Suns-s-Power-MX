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
        Schema::table('service_order_evidences', function (Blueprint $table) {
            $table->boolean('allows_multiple')->default(false);
        });
    }

    public function down()
    {
        Schema::table('service_order_evidences', function (Blueprint $table) {
            $table->dropColumn('allows_multiple');
        });
    }
};
