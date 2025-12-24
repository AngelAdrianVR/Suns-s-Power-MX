<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

});


// ---------------------------- Rutas de productos --------------------------------
Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
Route::resource('productos', ProductController::class)->names('products')
    ->parameters(['productos' => 'product'])->middleware('auth');


// ---------------------------- Rutas de ordenes de servicio --------------------------------
Route::resource('ordenes-servicio', ServiceOrderController::class)->names('service-orders')
    ->parameters(['ordenes_servicio' => 'serviceOrder'])->middleware('auth');


// ---------------------------- Rutas de ordenes de compras --------------------------------
Route::resource('ordenes-compras', PurchaseOrderController::class)->names('purchases')
    ->parameters(['ordenes_compras' => 'purchaseOrder'])->middleware('auth');


// ---------------------------- Rutas de proveedores --------------------------------
Route::resource('proveedores', SupplierController::class)->names('suppliers')
    ->parameters(['proveedores' => 'supplier'])->middleware('auth');


// ---------------------------- Rutas de Clientes --------------------------------
Route::resource('clientes', ClientController::class)->names('clients')
    ->parameters(['clientes' => 'client'])->middleware('auth');


// ---------------------------- Rutas de Usuarios --------------------------------
Route::middleware('auth')->group(function () {
    Route::patch('/usuarios/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status'); // Ruta específica para activar/desactivar usuario (Patch es ideal para actualizaciones parciales)
    Route::resource('usuarios', UserController::class)->names('users')->parameters(['usuarios' => 'user']);
});


// eliminacion de archivo desde componente FileView
Route::delete('/media/{media}', function (Media $media) {
    try {
        $media->delete(); // Elimina el archivo y su registro

        return response()->json(['message' => 'Archivo eliminado correctamente.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al eliminar el archivo.'], 500);
    }
})->name('media.delete-file');