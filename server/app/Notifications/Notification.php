<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;
use NotificationChannels\Expo\ExpoMessage;

abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['expo', 'database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * Get the expo representation of the notification.
     */
    public function toExpo(object $notifiable): ExpoMessage
    {
        return ExpoMessage::create($this->getNotificationTitle())
            ->body(Str::limit($this->getNotificationMessage(), 500))
            ->data($this->getNotificationData())
            ->channelId($this->getNotificationChannelId())
            ->high()
            ->expiresAt($this->getNotificationExpiry());
    }

    /**
     * Must match the Android notification channel the app creates.
     */
    protected function getNotificationChannelId(): string
    {
        return 'default';
    }

    /**
     * How long Expo/Google keep retrying if the phone is offline.
     */
    protected function getNotificationExpiry(): \DateTimeInterface
    {
        return now()->addHours(24);
    }

    /**
     * Get the notification type for styling.
     */
    protected function getNotificationSeverity(): string
    {
        return 'info';
    }

    /**
     * Get the notification title.
     */
    abstract protected function getNotificationTitle(): string;

    /**
     * Get the notification message.
     */
    abstract protected function getNotificationMessage(): string;

    /**
     * Get additional data for the notification.
     */
    protected function getNotificationData(): array
    {
        return [];
    }

    /**
     * Build the base array representation.
     */
    protected function buildNotificationArray(): array
    {
        return [
            'title' => $this->getNotificationTitle(),
            'message' => $this->getNotificationMessage(),
            'severity' => $this->getNotificationSeverity(),
            'data' => $this->getNotificationData(),
        ];
    }

    /**
     * Get the notifiable's name.
     */
    protected function getNotifiableName(object $notifiable): string
    {
        return $notifiable->name ?? 'User';
    }
}
