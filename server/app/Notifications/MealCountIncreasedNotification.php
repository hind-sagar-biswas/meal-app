<?php

namespace App\Notifications;

use App\Models\Meal;
use App\Models\User;
use Carbon\Carbon;

class MealCountIncreasedNotification extends Notification
{
    public function __construct(
        public Meal $meal,
        public User $editor,
        public string $mealType,
        public int $oldCount,
        public int $newCount,
        public string $note
    ) {}

    public function toArray(object $notifiable): array
    {
        return $this->buildNotificationArray();
    }

    protected function getNotificationTitle(): string
    {
        $dateFormatted = Carbon::parse($this->meal->date)->format('M d');
        $mealTypeName = ucfirst($this->mealType);

        return "Guest Meals Added: {$this->editor->name} ({$dateFormatted})";
    }

    protected function getNotificationMessage(): string
    {
        $dateFormatted = Carbon::parse($this->meal->date)->format('M d');
        $mealTypeName = ucfirst($this->mealType);

        return "{$this->editor->name} increased {$dateFormatted} {$mealTypeName} from {$this->oldCount} to {$this->newCount}. Note: {$this->note}";
    }

    protected function getNotificationSeverity(): string
    {
        return 'info';
    }

    protected function getNotificationData(): array
    {
        return [
            'type' => 'meal.count_increased',
            'meal_id' => $this->meal->id,
            'date' => Carbon::parse($this->meal->date)->toDateString(),
            'meal_type' => $this->mealType,
            'old_count' => $this->oldCount,
            'new_count' => $this->newCount,
            'editor_id' => $this->editor->id,
            'editor_name' => $this->editor->name,
            'note' => $this->note,
        ];
    }
}
