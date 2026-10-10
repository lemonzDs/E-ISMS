<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WorkspaceNotification extends Notification
{
    public function __construct(public string $message, public string $kind, public int $targetId) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'kind' => $this->kind, 'target_id' => $this->targetId];
    }
}
