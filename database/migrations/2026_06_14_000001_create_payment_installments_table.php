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
        Schema::create('payment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->onDelete('cascade');
            $table->integer('installment_number');
            $table->string('label');
            $table->date('projected_date');
            $table->decimal('amount', 12, 2);
            $table->enum('status', [
                'pending', 'paid', 'on_time', 'late', 'defaulted', 'upcoming'
            ])->default('pending');
            $table->decimal('paid_amount', 12, 2)->nullable()->default(0);
            $table->date('paid_date')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_installments');
    }
};
