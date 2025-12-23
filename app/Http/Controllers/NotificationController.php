<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetPasswordAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Maneja la solicitud desde la vista "Olvide mi contraseña".
     */
    public function requestPasswordReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // 1. Encontrar al usuario que solicita
        $user = User::with('branch')->where('email', $request->email)->first();

        if (!$user->branch_id) {
            return back()->with('status', 'El usuario no está asignado a ninguna sucursal.');
        }

        // 2. Encontrar administradores de ESA misma sucursal
        // Asumiendo que usas Spatie Laravel Permission: role('Admin')
        $admins = User::role('Admin')
                    ->where('branch_id', $user->branch_id)
                    ->where('is_active', true) // Buena práctica: solo admins activos
                    ->get();

        if ($admins->isEmpty()) {
            return back()->with('status', 'No se encontraron administradores en tu sucursal para notificar.');
        }

        // 3. Enviar la notificación a la colección de admins
        Notification::send($admins, new ResetPasswordAdminNotification($user));

        return back()->with('status', 'Se ha notificado a los administradores de tu sucursal (' . $user->branch->name . ').');
    }

    // ---------------- MÉTODOS PARA EL DROPDOWN DE VUE ---------------- //

    public function markAsRead($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return back();
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function destroy($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->delete();
        return back();
    }

    public function destroySelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:notifications,id',
        ]);

        auth()->user()->notifications()->whereIn('id', $request->ids)->delete();
        return back();
    }
}