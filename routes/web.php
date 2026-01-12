<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController; // Importar el controlador
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Artisan;

// Ruta Raíz: Muestra el estado de carga (animación)
Route::get('/', function () {
    return Inertia::render('Loading');
});

Route::get('/inicio', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// ---------------------------- RUTA PARA CAMBIAR SUCURSAL DESDE APPLAYOUT (BRANCH CONTEXT) --------------------------------
Route::post('/branch/switch', [App\Http\Controllers\BranchContextController::class, 'switch'])->name('branch.switch')->middleware('auth');


// --- RUTA PÚBLICA PARA SOLICITAR RESTABLECIMIENTO (NOTIFICACIÓN) ---
Route::post('/solicitar-restablecimiento', [NotificationController::class, 'requestPasswordReset'])
    ->name('password.request.notification');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Rutas de Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- RUTAS DE NOTIFICACIONES (PARA EL DROPDOWN) ---
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/destroy-selected', [NotificationController::class, 'destroySelected'])->name('notifications.destroy-selected');

    // ---------------------------- NUEVO: RUTAS DE CONFIGURACIÓN / ROLES ----------------------------
    // Se recomienda proteger esto con un middleware tipo 'role:admin' o similar
    Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);
    
    // Rutas extra para CRUD de permisos (Solo Developer ID 1 validado en controlador)
    Route::post('/permissions', [RoleController::class, 'storePermission'])->name('permissions.store');
    Route::put('/permissions/{permission}', [RoleController::class, 'updatePermission'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [RoleController::class, 'destroyPermission'])->name('permissions.destroy');
});


// ---------------------------- Rutas de productos --------------------------------
Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust_stock');
Route::get('/products/{product}/history', [ProductController::class, 'getHistory'])->name('products.history');
Route::resource('productos', ProductController::class)->names('products')
    ->parameters(['productos' => 'product'])->middleware('auth');


// ---------------------------- Rutas de ordenes de servicio --------------------------------
// Ruta específica para actualizar solo el estatus (método PATCH)
Route::patch('ordenes-servicio/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus'])->name('service-orders.update-status');
// Nueva ruta para subir evidencias (Media Library)
Route::post('ordenes-servicio/{serviceOrder}/media', [ServiceOrderController::class, 'uploadMedia'])->name('service-orders.upload-media');
// Recurso principal de órdenes de servicio CRUD
Route::resource('ordenes-servicio', ServiceOrderController::class)->names('service-orders')
    ->parameters(['ordenes-servicio' => 'serviceOrder'])->middleware('auth');
// RUTAS PARA PRODUCTOS / MATERIALES (Stock)
Route::post('/service-orders/{serviceOrder}/items', [ServiceOrderController::class, 'addItems'])->name('service-orders.add-items'); 
Route::delete('/service-orders/items/{item}', [ServiceOrderController::class, 'removeItem'])->name('service-orders.remove-item');


// ---------------------------- Rutas de Tickets (Soporte) --------------------------------
// Ruta para AGREGAR RESPUESTA/COMENTARIO (Reply)
Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
// Ruta para actualización rápida de estatus
Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
// Resource principal
Route::resource('tickets', TicketController::class)->names('tickets')
    ->parameters(['tickets' => 'ticket'])->middleware('auth');


// ---------------------------- Rutas de Tareas --------------------------------
Route::resource('tareas', TaskController::class)->only(['store', 'update', 'destroy'])->parameters(['tareas' => 'task'])
    ->names('tasks')->middleware('auth');


// Ruta para Comentarios Generales
Route::post('/comments', [CommentController::class, 'store'])->name('comments.store')->middleware('auth');


// ---------------------------- Rutas de ordenes de compras --------------------------------
Route::resource('ordenes-compra', PurchaseOrderController::class)->names('purchases')
    ->parameters(['ordenes-compra' => 'purchaseOrder'])->middleware('auth');
// Ruta adicional para cambio rápido de status (ej. recibir o cancelar desde el index)
Route::patch('/ordenes-compras/{purchaseOrder}/status', [PurchaseOrderController::class, 'changeStatus'])->name('purchases.status')->middleware('auth');
Route::get('/purchases/{purchaseOrder}/print', [PurchaseOrderController::class, 'printOrder'])->name('purchases.print');


// ---------------------------- Rutas de proveedores --------------------------------
Route::resource('proveedores', SupplierController::class)->names('suppliers')
    ->parameters(['proveedores' => 'supplier'])->middleware('auth');
    // --- RUTA PARA CARGA ASÍNCRONA DE PRODUCTOS EN SHOW ---
Route::get('/proveedores/{supplier}/fetch-available', [SupplierController::class, 'fetchAvailableProducts'])
    ->name('suppliers.products.fetch');
// Rutas Específicas para la asignación de productos (UI Show)
Route::post('/proveedores/{supplier}/products', [SupplierController::class, 'assignProduct'])->name('suppliers.products.assign');
Route::delete('/proveedores/{supplier}/products/{product}', [SupplierController::class, 'detachProduct'])->name('suppliers.products.detach');
Route::get('/suppliers/{supplier}/assigned-products', [PurchaseOrderController::class, 'getSupplierProducts'])->name('suppliers.products.assigned');
// Ruta para recuperar los productos asignados al proveedor en la creación de orden de compra
Route::get('/suppliers/{supplier}/assigned-products', [SupplierController::class, 'fetchAssignedProducts'])->name('suppliers.products.assigned');


// ---------------------------- Rutas de Clientes --------------------------------
Route::resource('clientes', ClientController::class)->names('clients')
->parameters(['clientes' => 'client'])->middleware('auth');
// API interna para el componente Vue (obtener deudas)
Route::get('/api/clients/{client}/pending-orders', [PaymentController::class, 'getPendingOrders'])
->name('api.clients.pending-orders');
Route::post('/clients/{client}/documents', [ClientController::class, 'uploadDocument'])->name('clients.documents.store');


// ---------------------------- Rutas de categorías --------------------------------
Route::resource('categories', CategoryController::class);


// ---------------------------- Rutas de Pagos --------------------------------
Route::resource('/payments', PaymentController::class)->middleware('auth');


// ---------------------------- Rutas de Usuarios --------------------------------
Route::middleware('auth')->group(function () {
    Route::patch('/usuarios/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status'); // Ruta específica para activar/desactivar usuario (Patch es ideal para actualizaciones parciales)
    Route::resource('usuarios', UserController::class)->names('users')->parameters(['usuarios' => 'user']);
});


// eliminacion de archivo desde componente FileView
Route::delete('/media/{media}', function (Media $media) {
    try {
        $media->delete(); // Elimina el archivo y su registro

        // return response()->json(['message' => 'Archivo eliminado correctamente.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al eliminar el archivo.'], 500);
    }
})->name('media.delete-file');


// Ruta para crear el enlace simbólico de storage (si no se ha creado aún)
Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'storage:link ejecutado correctamente';
});