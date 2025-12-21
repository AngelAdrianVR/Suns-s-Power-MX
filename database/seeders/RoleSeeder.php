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
        Permission::create(['name' => 'ver_inventario']);
        Permission::create(['name' => 'crear_producto']);
        Permission::create(['name' => 'editar_producto']);
        Permission::create(['name' => 'ajustar_stock']); // Solo almacén o admin
        
        // Ventas y CRM
        Permission::create(['name' => 'ver_clientes']);
        Permission::create(['name' => 'crear_cliente']);
        Permission::create(['name' => 'crear_cotizacion']);
        Permission::create(['name' => 'ver_precios_compra']); // Sensible: Ocultar costos a vendedores si quieres
        
        // Operaciones y Técnica
        Permission::create(['name' => 'ver_ordenes_servicio']);
        Permission::create(['name' => 'finalizar_instalacion']);
        Permission::create(['name' => 'ver_tablero_tareas']);
        
        // Admin
        Permission::create(['name' => 'gestionar_usuarios']);
        Permission::create(['name' => 'ver_reportes_financieros']);

        // 3. Crear Roles y Asignar Permisos

        // Role: Admin (Todo)
        $roleAdmin = Role::create(['name' => 'Admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        // Role: Ventas
        $roleVentas = Role::create(['name' => 'Ventas']);
        $roleVentas->givePermissionTo([
            'ver_inventario',
            'ver_clientes',
            'crear_cliente',
            'crear_cotizacion',
            'ver_ordenes_servicio', // Solo ver estado
            'ver_tablero_tareas'
        ]);

        // Role: Técnico
        $roleTecnico = Role::create(['name' => 'Técnico']);
        $roleTecnico->givePermissionTo([
            'ver_ordenes_servicio',
            'finalizar_instalacion', // Importante para cerrar orden
            'ver_tablero_tareas'
        ]);

        // Role: Almacén
        $roleAlmacen = Role::create(['name' => 'Almacén']);
        $roleAlmacen->givePermissionTo([
            'ver_inventario',
            'crear_producto',
            'editar_producto',
            'ajustar_stock'
        ]);
    }
}
