<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        // Sucursal Principal
        Branch::create([
            'name' => 'Matriz Guadalajara',
            'address' => 'Av. Vallarta 5050, Zapopan, Jalisco',
            'phone' => '3312345678',
            'is_active' => true,
        ]);

        // Sucursal Secundaria
        Branch::create([
            'name' => 'Sucursal CDMX',
            'address' => 'Av. Reforma 222, Ciudad de México',
            'phone' => '5512345678',
            'is_active' => true,
        ]);

        // Sucursal Norte (para pruebas de inactividad o expansión)
        Branch::create([
            'name' => 'Bodega Monterrey',
            'address' => 'Zona Industrial, Monterrey, NL',
            'phone' => '8112345678',
            'is_active' => true,
        ]);
    }
}