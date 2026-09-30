<?php

namespace App\Notifications;

use App\Models\Month;
use App\Models\User;
use Carbon\Carbon;

class MonthReopenedNotification extends Notification
{
    public function __construct(
        public Month $month,
        public User $reopenedBy
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $monthName = Carbon::createFromDate($this->month->year, $this->month->month, 1)->format('F Y');

        return "Month Reopened: {$monthName}";
    }

    protected function getNotificationMessage(): string
    {
        $monthName = Carbon::createFromDate($this->month->year, $this->month->month, 1)->format('F Y');

        return "{$this->reopenedBy->name} reopened {$monthName} for adjustments.";
    }

    protected function getNotificationSeverity(): string
    {
        return 'warning';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'month.reopened',
            'month_id' => $this->month->id,
            'year' => $this->month->year,
            'month' => $this->month->month,
            'reopened_by_id' => $this->reopenedBy->id,
            'reopened_by_name' => $this->reopenedBy->name,
        ];
    }
}
