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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('interest_amount', 12, 2)
                ->default(0)
                ->after('amount')
                ->comment('Porción de interés moratorio separada del abono. No cuenta para el saldo, solo se acumula como interés cobrado.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('interest_amount');
        });
    }
};
