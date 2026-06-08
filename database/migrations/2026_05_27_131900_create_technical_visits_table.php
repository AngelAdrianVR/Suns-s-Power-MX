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
        Schema::create('technical_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->default(1)->constrained()->cascadeOnDelete();
            
            // Relación con el prospecto/cliente y el vendedor
            $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete(); 
             $table->foreignId('sales_rep_id')->constrained('users')->comment('Vendedor asignado');
             $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();

            // Datos del Prospecto en la visita (antes de convertirse a cliente/orden)
            $table->string('business_name')->nullable(); // Razón Social
            $table->string('first_name')->nullable(); // Nombre del contacto
            $table->string('paternal_surname')->nullable(); // Apellido Paterno
            $table->string('maternal_surname')->nullable();  // Apellido Materno
            $table->string('phone')->nullable(); // Teléfono
            
            // Programación y Estatus
            $table->dateTime('scheduled_at');
            $table->enum('status', ['Pendiente', 'Reprogramada', 'Aceptada', 'Terminada', 'Rechazada'])->default('Pendiente');
            $table->text('reschedule_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Datos del Inmueble y Servicio
            $table->string('service_number')->nullable();
            $table->enum('rate_type', ['01', '1A', '1B', '1C', '1D', '1E', '1F', 'DAC', 'PDBT', 'GDBT', 'GDMTO', 'GDMTH', '00'])->nullable();
            $table->enum('property_use', ['Residencial', 'Comercial', 'Industrial'])->nullable();
            $table->boolean('requires_long_ladder')->default(false);
            $table->integer('property_floors')->nullable();
            $table->integer('number_of_wires')->nullable();
            $table->decimal('voltage', 8, 2)->nullable()->comment('Voltaje medido en la visita (V)');
            $table->string('google_maps_link')->nullable();

            // Dirección Desglosada (Address Splitting)
            $table->string('road_type')->nullable();        // NUEVO: Tipo de vialidad (Calle, Avenida, Blvd)
            $table->string('street')->nullable();           // Calle
            $table->string('exterior_number')->nullable();  // Número Exterior
            $table->string('interior_number')->nullable();  // Número Interior
            $table->string('neighborhood')->nullable();     // Colonia
            $table->string('municipality')->nullable();     // Municipio / Alcaldía
            $table->string('state')->nullable();            // Estado
            $table->string('zip_code')->nullable();         // Código Postal
            $table->string('country')->default('México');   // País
            
            // Notas internas
            $table->text('internal_notes')->nullable();
            
            // Sistema de Interés
            $table->enum('system_of_interest', ['Interconectado', 'Autónomo', 'Back-up', 'Bombeo'])->nullable();
            
            // Módulos y Costos
            $table->integer('module_quantity')->nullable();
            $table->string('module_brand')->nullable();
            $table->decimal('module_capacity', 10, 2)->nullable()->comment('En Wp');
            $table->decimal('budget', 12, 2)->nullable();
            
            // Cálculos Automáticos
            $table->decimal('gross_installed_capacity', 10, 2)->nullable()->comment('En kWp');
            $table->decimal('estimated_daily_generation', 10, 2)->nullable()->comment('En kWh');
            $table->decimal('estimated_monthly_generation', 10, 2)->nullable()->comment('En kWh');
            $table->decimal('estimated_monthly_saving', 12, 2)->nullable()->comment('En MXN');
            
            // Baterías (Autónomo / Back-up)
            $table->integer('battery_quantity')->nullable();
            $table->string('battery_brand')->nullable();
            $table->decimal('battery_capacity', 10, 2)->nullable()->comment('En kWh');
            $table->json('backup_devices')->nullable()->comment('Lista de equipos a respaldar (concepto, horas)');
            
            // Propuesta Comercial y Acondicionamiento
            $table->enum('payment_method', ['Contado', '3 MSI', '6 MSI', '9 MSI', '12 MSI', 'Personalizado'])->nullable();
            $table->boolean('requires_pre_installation')->default(false);
            $table->text('pre_installation_details')->nullable();
            $table->enum('pre_installation_assigned_to', ['Sun\'s power mx', 'Cliente', 'Otro'])->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_visits');
    }
};
