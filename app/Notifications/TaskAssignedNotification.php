<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\User;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public $task;
    public $assignedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $assignedBy)
    {
        $this->task = $task;
        $this->assignedBy = $assignedBy;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $contexto = '';
        if ($this->task->taskable_type === 'App\\Models\\ServiceOrder') {
            $contexto = " en la orden #" . $this->task->taskable_id;
        } elseif ($this->task->taskable_type === 'App\\Models\\Ticket') {
            $contexto = " en el ticket #" . $this->task->taskable_id;
        }

        return [
            'title' => 'Nueva Tarea Asignada',
            'message' => "<b>{$this->assignedBy->name}</b> te ha asignado la tarea <b>{$this->task->title}</b>{$contexto}.",
            'icon' => 'fa-solid fa-briefcase',
            'url' => route('tasks.index'), // Redirige al dashboard de tareas
            'type' => 'task_assignment',
            'task_id' => $this->task->id
        ];
    }
}