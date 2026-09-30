<?php

namespace App\Notifications;

use App\Models\Month;
use App\Models\MonthResult;
use App\Models\User;
use Carbon\Carbon;

class MonthClosedNotification extends Notification
{
    public function __construct(
        public Month $month,
        public MonthResult $result,
        public User $closedBy
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $monthName = Carbon::createFromDate($this->month->year, $this->month->month, 1)->format('F Y');

        return "Month Closed: {$monthName}";
    }

    protected function getNotificationMessage(): string
    {
        $monthName = Carbon::createFromDate($this->month->year, $this->month->month, 1)->format('F Y');
        $mealRate = number_format($this->result->meal_rate / 1_000_000, 2);

        return "{$this->closedBy->name} closed {$monthName}. Meal rate: {$mealRate} Tk. Check your final balance.";
    }

    protected function getNotificationSeverity(): string
    {
        return 'success';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'month.closed',
            'month_id' => $this->month->id,
            'year' => $this->month->year,
            'month' => $this->month->month,
            'meal_rate' => $this->result->meal_rate / 1_000_000,
            'group_expense_per_person' => $this->result->group_expense_per_person / 1_000_000,
            'closed_by_id' => $this->closedBy->id,
            'closed_by_name' => $this->closedBy->name,
        ];
    }
}
