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
        // 1. Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definir Permisos por Módulo
        $permissions = [
            // --- Módulo: Productos ---
            [
                'name' => 'products.index',
                'description' => 'Ver listado de productos e inventario',
                'module' => 'Productos'
            ],
            [
                'name' => 'products.create',
                'description' => 'Registrar nuevos productos en el catálogo',
                'module' => 'Productos'
            ],
            [
                'name' => 'products.edit',
                'description' => 'Editar información de productos existentes',
                'module' => 'Productos'
            ],
            [
                'name' => 'products.delete',
                'description' => 'Eliminar productos del sistema',
                'module' => 'Productos'
            ],
            [
                'name' => 'products.adjust_stock',
                'description' => 'Realizar ajustes manuales de inventario',
                'module' => 'Productos'
            ],
            [
                'name' => 'products.view_costs',
                'description' => 'Ver precios de costo y márgenes de utilidad',
                'module' => 'Productos'
            ],

            // --- Módulo: Ordenes de servicio ---
            [
                'name' => 'service_orders.index',
                'description' => 'Ver listado de órdenes de servicio',
                'module' => 'Ordenes de servicio'
            ],
            [
                'name' => 'service_orders.create',
                'description' => 'Crear nuevas órdenes de servicio (Cotizaciones)',
                'module' => 'Ordenes de servicio'
            ],
            [
                'name' => 'service_orders.edit',
                'description' => 'Editar detalles de las órdenes de servicio',
                'module' => 'Ordenes de servicio'
            ],
            [
                'name' => 'service_orders.delete',
                'description' => 'Eliminar órdenes de servicio',
                'module' => 'Ordenes de servicio'
            ],
            [
                'name' => 'service_orders.change_status',
                'description' => 'Cambiar el estatus (Aceptar, Iniciar, Finalizar)',
                'module' => 'Ordenes de servicio'
            ],
            [
                'name' => 'tasks.view_board',
                'description' => 'Ver el tablero de tareas operativas',
                'module' => 'Ordenes de servicio'
            ],

            // --- Módulo: Compras ---
            [
                'name' => 'purchases.index',
                'description' => 'Ver historial de órdenes de compra',
                'module' => 'Compras'
            ],
            [
                'name' => 'purchases.create',
                'description' => 'Generar nuevas órdenes de compra a proveedores',
                'module' => 'Compras'
            ],
            [
                'name' => 'purchases.edit',
                'description' => 'Editar órdenes de compra existentes',
                'module' => 'Compras'
            ],
            [
                'name' => 'purchases.delete',
                'description' => 'Eliminar órdenes de compra',
                'module' => 'Compras'
            ],
            [
                'name' => 'purchases.approve',
                'description' => 'Aprobar y procesar órdenes de compra',
                'module' => 'Compras'
            ],
            [
                'name' => 'suppliers.index',
                'description' => 'Ver listado de proveedores',
                'module' => 'Compras'
            ],
            [
                'name' => 'suppliers.create',
                'description' => 'Registrar nuevos proveedores',
                'module' => 'Compras'
            ],
            [
                'name' => 'suppliers.edit',
                'description' => 'Editar información de proveedores',
                'module' => 'Compras'
            ],
            [
                'name' => 'suppliers.delete',
                'description' => 'Eliminar proveedores',
                'module' => 'Compras'
            ],

            // --- Módulo: Clientes ---
            [
                'name' => 'clients.index',
                'description' => 'Ver directorio de clientes',
                'module' => 'Clientes'
            ],
            [
                'name' => 'clients.create',
                'description' => 'Registrar nuevos clientes en el sistema',
                'module' => 'Clientes'
            ],
            [
                'name' => 'clients.edit',
                'description' => 'Editar información de contacto de clientes',
                'module' => 'Clientes'
            ],
            [
                'name' => 'clients.delete',
                'description' => 'Eliminar clientes',
                'module' => 'Clientes'
            ],

            // --- Módulo: Tickets ---
            [
                'name' => 'tickets.index',
                'description' => 'Ver listado de tickets de soporte',
                'module' => 'Tickets'
            ],
            [
                'name' => 'tickets.create',
                'description' => 'Levantar nuevos tickets de soporte',
                'module' => 'Tickets'
            ],
            [
                'name' => 'tickets.manage',
                'description' => 'Responder, editar y cerrar tickets',
                'module' => 'Tickets'
            ],
            [
                'name' => 'tickets.delete',
                'description' => 'Eliminar tickets',
                'module' => 'Tickets'
            ],

            // --- Módulo: Usuarios ---
            [
                'name' => 'users.index',
                'description' => 'Ver lista de usuarios del sistema',
                'module' => 'Usuarios'
            ],
            [
                'name' => 'users.create',
                'description' => 'Registrar nuevos usuarios',
                'module' => 'Usuarios'
            ],
            [
                'name' => 'users.edit',
                'description' => 'Editar datos de usuarios y asignar roles',
                'module' => 'Usuarios'
            ],
            [
                'name' => 'users.toggle_status',
                'description' => 'Activar o desactivar acceso a usuarios',
                'module' => 'Usuarios'
            ],
            [
                'name' => 'users.assign_tasks',
                'description' => 'Asignar tareas a usuarios',
                'module' => 'Usuarios'
            ],

            // --- Módulo: Configuraciones ---
            [
                'name' => 'roles.index',
                'description' => 'Ver roles y permisos existentes',
                'module' => 'Configuraciones'
            ],
            [
                'name' => 'roles.manage',
                'description' => 'Crear, editar y eliminar roles',
                'module' => 'Configuraciones'
            ],
            [
                'name' => 'settings.general',
                'description' => 'Acceder a configuraciones generales del sistema',
                'module' => 'Configuraciones'
            ],
        ];

        // 3. Insertar Permisos en Base de Datos
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                    'guard_name' => 'web'
                ]
            );
        }

        // 4. Crear Roles y Asignar Permisos

        // Role: Admin (Acceso Total)
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin']);
        // El admin tiene todos los permisos existentes
        $roleAdmin->syncPermissions(Permission::all());

        // Role: Ventas
        $roleVentas = Role::firstOrCreate(['name' => 'Ventas']);
        $roleVentas->syncPermissions([
            'products.index',
            'clients.index',
            'clients.create',
            'clients.edit',
            'service_orders.index',
            'service_orders.create', // Cotizar
            'tasks.view_board',
            'tickets.create',
        ]);

        // Role: Técnico
        $roleTecnico = Role::firstOrCreate(['name' => 'Técnico']);
        $roleTecnico->syncPermissions([
            'service_orders.index',
            'service_orders.edit', // Para actualizar notas
            'service_orders.change_status', // Para finalizar
            'tasks.view_board',
            'tickets.index',
            'tickets.manage', // Responder tickets asignados
        ]);

        // Role: Almacén
        $roleAlmacen = Role::firstOrCreate(['name' => 'Almacén']);
        $roleAlmacen->syncPermissions([
            'products.index',
            'products.create',
            'products.edit',
            'products.adjust_stock',
            'purchases.index',
            'purchases.create',
            'suppliers.create',
        ]);
    }
}