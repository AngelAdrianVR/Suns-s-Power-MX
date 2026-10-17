<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opción por documento del expediente para imprimir todos sus archivos
     * (frente y reverso del INE, etc.) en una sola hoja.
     */
    public function up(): void
    {
        Schema::table('service_documentation_steps', function (Blueprint $table) {
            $table->boolean('single_sheet')->default(false)->after('order');
            // auto | stacked | side_by_side
            $table->string('sheet_layout')->default('auto')->after('single_sheet');
        });
    }

    public function down(): void
    {
        Schema::table('service_documentation_steps', function (Blueprint $table) {
            $table->dropColumn(['single_sheet', 'sheet_layout']);
        });
    }
};
