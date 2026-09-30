<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\Carbon;

class DateRangeMealOffNotification extends Notification
{
    public function __construct(
        public Carbon|string $startDate,
        public Carbon|string $endDate,
        public User $editor,
        public string $note
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $startFormatted = Carbon::parse($this->startDate)->format('M d');
        $endFormatted = Carbon::parse($this->endDate)->format('M d');

        return "Holiday Meals Off ({$startFormatted} - {$endFormatted})";
    }

    protected function getNotificationMessage(): string
    {
        $startFormatted = Carbon::parse($this->startDate)->format('M d');
        $endFormatted = Carbon::parse($this->endDate)->format('M d');

        return "{$this->editor->name} turned off all meals from {$startFormatted} to {$endFormatted}. Note: {$this->note}";
    }

    protected function getNotificationSeverity(): string
    {
        return 'info';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'meal.date_range_off',
            'start_date' => Carbon::parse($this->startDate)->toDateString(),
            'end_date' => Carbon::parse($this->endDate)->toDateString(),
            'editor_id' => $this->editor->id,
            'editor_name' => $this->editor->name,
            'note' => $this->note,
        ];
    }
}
