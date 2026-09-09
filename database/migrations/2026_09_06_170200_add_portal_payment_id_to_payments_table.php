<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad: vincula un pago del ERP con el abono del portal
     * (`portal_payments`) que le dio origen al validarse.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('portal_payment_id')
                ->nullable()
                ->after('service_order_id')
                ->constrained('portal_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('portal_payment_id');
        });
    }
};
