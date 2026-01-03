<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /**
     * Guarda un nuevo comentario y notifica al técnico encargado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
        ]);

        $user = Auth::user();

        // 1. Crear el comentario
        $comment = Comment::create([
            'body' => $validated['body'],
            'commentable_type' => $this->mapCommentableType($validated['commentable_type']), // Helper para mapear string a Modelo
            'commentable_id' => $validated['commentable_id'],
            'user_id' => $user->id,
        ]);

        // 2. Lógica de Notificación para Tareas
        if ($validated['commentable_type'] === 'task') {
            $this->notifyTaskTechnician($comment, $validated['commentable_id'], $user);
        }

        return redirect()->back()->with('success', 'Comentario agregado correctamente.');
    }

    /**
     * Notifica al técnico encargado de la orden asociada a la tarea.
     */
    private function notifyTaskTechnician(Comment $comment, int $taskId, User $sender)
    {
        // Cargamos la tarea y su orden de servicio relacionada
        $task = Task::with('serviceOrder')->find($taskId);

        if ($task && $task->serviceOrder) {
            // Obtenemos el ID del técnico encargado desde la orden
            $technicianId = $task->serviceOrder->technician_id;

            // Verificamos que exista un técnico y que NO sea la misma persona que está comentando
            if ($technicianId && $technicianId !== $sender->id) {
                $technician = User::find($technicianId);
                
                if ($technician) {
                    $technician->notify(new NewCommentNotification($comment, $task, $sender));
                }
            }
        }
    }

    /**
     * Helper para mapear el tipo de comentario al modelo correspondiente.
     * Esto es útil si usas MorphMap o necesitas el namespace completo.
     */
    private function mapCommentableType($type)
    {
        return match ($type) {
            'task' => Task::class, // O 'App\Models\Task'
            'service_order' => \App\Models\ServiceOrder::class,
            default => null,
        };
    }
}