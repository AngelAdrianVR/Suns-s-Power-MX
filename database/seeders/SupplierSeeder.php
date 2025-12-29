<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::create([
            'company_name' => 'SolarTech Distribución',
            'contact_name' => 'Roberto Gómez',
            'email' => 'ventas@solartech.mx',
            'phone' => '3333333333',
        ]);

        Supplier::create([
            'company_name' => 'Energy Solutions Global',
            'contact_name' => 'Ana Martínez',
            'email' => 'contacto@energyglobal.com',
            'phone' => '5555555555',
        ]);

        Supplier::create([
            'company_name' => 'Ferretería Industrial del Norte',
            'contact_name' => 'Carlos Ruiz',
            'email' => 'cruiz@ferrenorte.mx',
            'phone' => '8181818181',
        ]);
    }
}