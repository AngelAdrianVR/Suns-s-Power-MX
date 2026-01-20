<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Branch;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear Sucursales primero (necesario para usuarios y FKs)
        $this->call(BranchSeeder::class);

        // Obtenemos la sucursal principal para asignarla a los usuarios
        $mainBranch = Branch::first();

        // 2. Crear Roles y Permisos
        $this->call(RoleSeeder::class);

        // 3. Crear Usuario ADMINISTRADOR (Soporte)
        // ACTUALIZADO: Agregamos first_name, apellidos y datos legales básicos para evitar error
        $admin = User::create([
            'first_name' => 'Angel',
            'paternal_surname' => 'Soporte',
            'maternal_surname' => 'Admin',
            'name' => 'Angel Soporte Admin', // Mantenemos concatenado por compatibilidad
            'email' => 'angel@gmail.com',
            'password' => Hash::make('321321321'),
            'branch_id' => $mainBranch->id,
            'is_active' => true,
            'email_verified_at' => now(),
            
            // Datos complementarios para evitar errores de campos requeridos
            'phone' => '5500000000',
            'curp' => 'XAXX010101HNEZR00', // Genérico
            'rfc' => 'XAXX010101000',      // Genérico
            'nss' => '00000000000',
            
            // Domicilio básico (opcional, pero recomendado inicializar)
            'street' => 'Conocido',
            'exterior_number' => 'S/N',
            'neighborhood' => 'Centro',
            'zip_code' => '00000',
            'municipality' => 'General',
            'state' => 'México',
        ]);
        
        // Asignar rol de Admin
        $admin->assignRole('Admin');


        // // 4. Crear Usuario EMPLEADO (Ejemplo actualizado)
        // $empleado = User::create([
        //     'first_name' => 'Juan',
        //     'paternal_surname' => 'Técnico',
        //     'maternal_surname' => '',
        //     'name' => 'Juan Técnico',
        //     'email' => 'tecnico@sunspower.mx',
        //     'password' => Hash::make('321321321'),
        //     'branch_id' => $mainBranch->id,
        //     'is_active' => true,
        //     'email_verified_at' => now(),
        //     'curp' => 'XAXX020202HNEZR00',
        // ]);

        // // Asignar rol de Técnico
        // $empleado->assignRole('Técnico'); // O 'Ventas' o 'Almacén' según prefieras


        // // 5. Llenar catálogos del sistema
        // $this->call([
        //     CategorySeeder::class,
        //     SupplierSeeder::class,
        //     ProductSeeder::class, // Esto también llena el inventario por sucursal
        //     ClientSeeder::class,
        // ]);
        
        // Opcional: Si tienes factories para generar volumen
        // User::factory(10)->create(); 
    }
}