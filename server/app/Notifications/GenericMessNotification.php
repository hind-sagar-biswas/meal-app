<?php

namespace App\Notifications;

use App\Models\User;

class GenericMessNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public User $sender,
        public ?string $note = null,
        public string $severity = 'info'
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        return $this->title;
    }

    protected function getNotificationMessage(): string
    {
        if ($this->note) {
            return "{$this->message} Note: {$this->note}";
        }

        return $this->message;
    }

    protected function getNotificationSeverity(): string
    {
        return $this->severity;
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'mess.generic',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'note' => $this->note,
        ];
    }
}
