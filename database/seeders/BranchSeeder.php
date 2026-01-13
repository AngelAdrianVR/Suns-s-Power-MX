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
            'name' => 'Sucursal Veracruz',
            'address' => 'Av. Benito Juárez 105, Col. Tajín, Poza Rica',
            'phone' => '782 210 32 14',
            'is_active' => true,
        ]);

        // Sucursal Secundaria
        Branch::create([
            'name' => 'Sucursal CDMX',
            'address' => 'Lago Chiem 45, Col. San Juanico, Miguel Hidalgo',
            'phone' => '55 20 68 05 32',
            'is_active' => true,
        ]);

    }
}