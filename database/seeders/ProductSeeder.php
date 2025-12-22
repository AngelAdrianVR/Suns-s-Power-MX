<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurarnos de tener categorías
        $catPaneles = Category::where('name', 'Paneles Solares')->first();
        $catInversores = Category::where('name', 'Microinversores')->first();
        $catEstructuras = Category::where('name', 'Estructuras de Montaje')->first();

        // 1. Crear Productos
        $products = [
            [
                'name' => 'Panel Solar 550W Monocristalino',
                'sku' => 'PNL-550W-MONO',
                'description' => 'Panel de alta eficiencia tecnología PERC',
                'purchase_price' => 2800.00,
                'sale_price' => 3500.00,
                'category_id' => $catPaneles->id ?? 1,
            ],
            [
                'name' => 'Panel Solar 450W Policristalino',
                'sku' => 'PNL-450W-POLI',
                'description' => 'Panel estándar para proyectos residenciales',
                'purchase_price' => 2100.00,
                'sale_price' => 2800.00,
                'category_id' => $catPaneles->id ?? 1,
            ],
            [
                'name' => 'Microinversor IQ7+',
                'sku' => 'INV-IQ7-PLUS',
                'description' => 'Microinversor compatible con paneles de 60 y 72 celdas',
                'purchase_price' => 2500.00,
                'sale_price' => 3200.00,
                'category_id' => $catInversores->id ?? 1,
            ],
            [
                'name' => 'Riel de Aluminio Anodizado 4m',
                'sku' => 'EST-RIEL-4M',
                'description' => 'Riel para montaje de estructura coplanar',
                'purchase_price' => 450.00,
                'sale_price' => 700.00,
                'category_id' => $catEstructuras->id ?? 1,
            ],
        ];

        $branches = Branch::all();

        foreach ($products as $data) {
            $product = Product::create($data);

            // 2. Asignar inventario a cada sucursal (branch_product)
            foreach ($branches as $branch) {
                // Simulamos stock diferente por sucursal
                $stock = rand(10, 100); 
                
                // Usamos DB table directamente para asegurar la inserción en la pivote 
                // independientemente de cómo se llame la relación en el modelo Product
                DB::table('branch_product')->insert([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'current_stock' => $stock,
                    'min_stock_alert' => 5,
                    'location_in_warehouse' => 'Pasillo ' . rand(1, 5) . ', Estante ' . rand(1, 10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}