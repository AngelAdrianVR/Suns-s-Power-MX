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

        // 3. Crear Usuario ADMINISTRADOR
        $admin = User::create([
            'name' => 'Soporte',
            'email' => 'angel@gmail.com',
            'password' => Hash::make('321321321'), // Contraseña segura
            'branch_id' => $mainBranch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        
        // Asignar rol de Admin
        $admin->assignRole('Admin');


        // 4. Crear Usuario EMPLEADO (Técnico o Ventas)
        $empleado = User::create([
            'name' => 'Juan Técnico',
            'email' => 'tecnico@sunspower.mx',
            'password' => Hash::make('321321321'),
            'branch_id' => $mainBranch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Asignar rol de Técnico
        $empleado->assignRole('Técnico'); // O 'Ventas' o 'Almacén' según prefieras


        // 5. Llenar catálogos del sistema
        $this->call([
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class, // Esto también llena el inventario por sucursal
            ClientSeeder::class,
        ]);
        
        // Opcional: Si tienes factories para generar volumen
        // User::factory(10)->create(); 
    }
}