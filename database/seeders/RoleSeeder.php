<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar caché de permisos por si acaso
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Crear Permisos (Operaciones atómicas)
        // Inventario
        Permission::create(['name' => 'Ver inventario']);
        Permission::create(['name' => 'Crear producto']);
        Permission::create(['name' => 'Editar producto']);
        Permission::create(['name' => 'Ajustar stock']); // Solo almacén o admin
        
        // Ventas y CRM
        Permission::create(['name' => 'Ver clientes']);
        Permission::create(['name' => 'Crear cliente']);
        Permission::create(['name' => 'Crear cotizacion']);
        Permission::create(['name' => 'Ver precios compra']); // Sensible: Ocultar costos a vendedores si quieres
        
        // Operaciones y Técnica
        Permission::create(['name' => 'Ver ordenes servicio']);
        Permission::create(['name' => 'finalizar instalacion']);
        Permission::create(['name' => 'Ver tablero tareas']);
        
        // Admin
        Permission::create(['name' => 'gestionar usuarios']);
        Permission::create(['name' => 'Ver reportes financieros']);

        // 3. Crear Roles y Asignar Permisos

        // Role: Admin (Todo)
        $roleAdmin = Role::create(['name' => 'Admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        // Role: Ventas
        $roleVentas = Role::create(['name' => 'Ventas']);
        $roleVentas->givePermissionTo([
            'Ver inventario',
            'Ver clientes',
            'Crear cliente',
            'Crear cotizacion',
            'Ver ordenes servicio', // Solo Ver estado
            'Ver tablero tareas'
        ]);

        // Role: Técnico
        $roleTecnico = Role::create(['name' => 'Técnico']);
        $roleTecnico->givePermissionTo([
            'Ver ordenes servicio',
            // 'Finalizar instalacion', // Importante para cerrar orden
            'Ver tablero tareas'
        ]);

        // Role: Almacén
        $roleAlmacen = Role::create(['name' => 'Almacén']);
        $roleAlmacen->givePermissionTo([
            'Ver inventario',
            'Crear producto',
            'Editar producto',
            'Ajustar stock'
        ]);
    }
}
