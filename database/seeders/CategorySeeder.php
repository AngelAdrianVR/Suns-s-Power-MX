<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Paneles Solares',
            'Inversores Centrales',
            'Microinversores',
            'Estructuras de Montaje',
            'Baterías y Almacenamiento',
            'Material Eléctrico (Cables y Protecciones)',
            'Monitoreo y Accesorios'
        ];

        foreach ($categories as $category) {
            Category::create(['name' => $category]);
        }
    }
}