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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained();
            $table->string('company_name');
            $table->string('website')->nullable();

            // Nuevos campos solicitados
            $table->string('rfc', 20)->nullable();
            $table->text('address')->nullable();
            
            // Datos Bancarios
            $table->string('bank_account_holder')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('clabe', 20)->nullable();
            $table->string('account_number', 20)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
