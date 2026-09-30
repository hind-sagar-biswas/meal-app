<?php

namespace App\Notifications;

use App\Models\Meal;
use App\Models\User;
use Carbon\Carbon;

class MemberMealEditedNotification extends Notification
{
    public function __construct(
        public Meal $meal,
        public User $editor,
        public array $before,
        public string $note
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $dateFormatted = Carbon::parse($this->meal->date)->format('M d');

        return "Meal Edited ({$dateFormatted})";
    }

    protected function getNotificationMessage(): string
    {
        $dateFormatted = Carbon::parse($this->meal->date)->format('M d');

        return "{$this->editor->name} updated your {$dateFormatted} meal to ({$this->meal->breakfast}, {$this->meal->lunch}, {$this->meal->dinner}). Note: {$this->note}";
    }

    protected function getNotificationSeverity(): string
    {
        return 'info';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'meal.member_edited',
            'meal_id' => $this->meal->id,
            'date' => Carbon::parse($this->meal->date)->toDateString(),
            'before' => $this->before,
            'after' => [
                'breakfast' => $this->meal->breakfast,
                'lunch' => $this->meal->lunch,
                'dinner' => $this->meal->dinner,
            ],
            'editor_id' => $this->editor->id,
            'editor_name' => $this->editor->name,
            'note' => $this->note,
        ];
    }
}
