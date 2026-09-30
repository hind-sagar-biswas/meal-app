<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\Carbon;

class DayMealTallyUpdatedNotification extends Notification
{
    public function __construct(
        public Carbon|string $date,
        public User $editor,
        public int $breakfast,
        public int $lunch,
        public int $dinner,
        public string $note
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $dateFormatted = Carbon::parse($this->date)->format('M d');

        return "Day Meal Tally Updated ({$dateFormatted})";
    }

    protected function getNotificationMessage(): string
    {
        $dateFormatted = Carbon::parse($this->date)->format('M d');

        return "{$this->editor->name} updated everyone's {$dateFormatted} meal to ({$this->breakfast}, {$this->lunch}, {$this->dinner}). Note: {$this->note}";
    }

    protected function getNotificationSeverity(): string
    {
        return 'info';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'meal.day_tally_updated',
            'date' => Carbon::parse($this->date)->toDateString(),
            'breakfast' => $this->breakfast,
            'lunch' => $this->lunch,
            'dinner' => $this->dinner,
            'editor_id' => $this->editor->id,
            'editor_name' => $this->editor->name,
            'note' => $this->note,
        ];
    }
}
