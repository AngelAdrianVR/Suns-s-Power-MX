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
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->boolean('apply_interest')
                ->default(true)
                ->after('amount')
                ->comment('Si se debe cobrar interés moratorio a esta cuota (false = registro tardío, el cliente pagó a tiempo)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_installments', function (Blueprint $table) {
            $table->dropColumn('apply_interest');
        });
    }
};
