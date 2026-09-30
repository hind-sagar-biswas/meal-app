<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\Carbon;

class DayMealOffNotification extends Notification
{
    public function __construct(
        public Carbon|string $date,
        public User $editor,
        public string $note
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $dateFormatted = Carbon::parse($this->date)->format('M d');

        return "All Meals Off ({$dateFormatted})";
    }

    protected function getNotificationMessage(): string
    {
        $dateFormatted = Carbon::parse($this->date)->format('M d');

        return "{$this->editor->name} marked all meals off for {$dateFormatted}. Note: {$this->note}";
    }

    protected function getNotificationSeverity(): string
    {
        return 'warning';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'meal.day_off',
            'date' => Carbon::parse($this->date)->toDateString(),
            'editor_id' => $this->editor->id,
            'editor_name' => $this->editor->name,
            'note' => $this->note,
        ];
    }
}
