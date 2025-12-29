<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class ResetPasswordAdminNotification extends Notification
{
    use Queueable;

    public $requester;

    /**
     * Recibimos el usuario que solicitó el cambio.
     */
    public function __construct(User $requester)
    {
        $this->requester = $requester;
    }

    /**
     * Definimos que se guardará en base de datos.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Estructura de datos para la tabla y el componente Vue.
     */
    public function toArray(object $notifiable): array
    {
        return [
            // Título corto
            'title' => 'Solicitud de Contraseña',
            
            // Mensaje con formato HTML simple (bold) para resaltar el nombre
            'message' => "El usuario <b>{$this->requester->name}</b> de la sucursal <b>{$this->requester->branch->name}</b> ha solicitado restablecer su contraseña.",
            
            // Icono para el frontend (FontAwesome o similar)
            'icon' => 'fa-solid fa-key',
            
            // URL a la que redirige al hacer clic (ej. al perfil del usuario para editarlo)
            'url' => route('users.edit', $this->requester->id),
            
            // Tipo de notificación para lógica frontend si fuera necesario
            'type' => 'security_alert'
        ];
    }
}