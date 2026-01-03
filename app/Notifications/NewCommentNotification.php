<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification; // Sin ShouldQueue para prueba inmediata
use App\Models\Task;
use App\Models\User;
use App\Models\Comment;

class NewCommentNotification extends Notification
{
    use Queueable;

    public $comment;
    public $task;
    public $sender;

    public function __construct(Comment $comment, Task $task, User $sender)
    {
        $this->comment = $comment;
        $this->task = $task;
        $this->sender = $sender;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // Truncar comentario
        $shortBody = \Illuminate\Support\Str::limit($this->comment->body, 50);
        
        // CORRECCIÓN AQUÍ: Usamos 'title' en lugar de 'name'
        // y nos aseguramos de que service_order_id exista
        $taskTitle = $this->task->title ?? 'Tarea sin título';
        $orderId = $this->task->service_order_id ?? 'N/A';

        return [
            'title' => 'Nuevo Comentario en Tarea',
            'message' => "<b>{$this->sender->name}</b> comentó en la tarea <b>{$taskTitle}</b> de la orden #<b>{$orderId}</b>: \"{$shortBody}\"",
            'icon' => 'fa-regular fa-comments',
            'url' => route('service-orders.show', $orderId),
            'type' => 'new_comment',
            'task_id' => $this->task->id,
            'comment_id' => $this->comment->id
        ];
    }
}