<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Branch;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = Branch::first()->id ?? 1;

        Client::create([
            'branch_id' => $branchId,
            'name' => 'Juan Pérez Residencial',
            'contact_person' => 'Juan Pérez',
            'email' => 'juan.cliente@gmail.com',
            'phone' => '3311223344',
            'address' => 'Calle Girasol 123, Col. Jardines',
            'notes' => 'Cliente interesado en sistema interconectado de 4 paneles',
        ]);

        Client::create([
            'branch_id' => $branchId,
            'name' => 'Industrias Metalmecánicas SA de CV',
            'contact_person' => 'Ing. Laura Sánchez',
            'email' => 'compras@indmetal.mx',
            'phone' => '3344556677',
            'tax_id' => 'IME800101XYZ',
            'address' => 'Parque Industrial Belenes Norte, Nave 4',
            'notes' => 'Proyecto industrial, requiere transformador',
        ]);
        
        // Generar algunos clientes más genéricos si tienes Factory, 
        // si no, con estos dos basta para pruebas funcionales.
    }
}