<?php

namespace App\Http\Resources;

use App\Models\Meal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meal
 */
class MealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $formattedDate = $this->date instanceof \DateTimeInterface
            ? $this->date->format('Y-m-d')
            : (is_string($this->date) ? Carbon::parse($this->date)->toDateString() : null);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'month_id' => $this->month_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'date' => $formattedDate,
            'breakfast' => (int) $this->breakfast,
            'lunch' => (int) $this->lunch,
            'dinner' => (int) $this->dinner,
            'total' => (int) ($this->breakfast + $this->lunch + $this->dinner),
            'has_logged' => (bool) $this->has_logged,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
