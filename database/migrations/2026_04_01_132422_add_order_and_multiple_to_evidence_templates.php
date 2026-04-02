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
        Schema::table('evidence_templates', function (Blueprint $table) {
            $table->integer('order')->default(0);
            $table->boolean('allows_multiple')->default(false);
        });
    }

    public function down()
    {
        Schema::table('evidence_templates', function (Blueprint $table) {
            $table->dropColumn(['order', 'allows_multiple']);
        });
    }
};
