<?php

namespace App\Http\Resources;

use App\Models\MonthResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MonthResult
 */
class MonthResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'month_id' => $this->month_id,
            'participant_count' => (int) $this->participant_count,
            'breakfast_count' => (int) $this->breakfast_count,
            'meal_count' => (int) $this->meal_count,
            'total_expense' => (int) $this->total_expense,
            'bazar_expense' => (int) $this->bazar_expense,
            'grouped_expense' => (int) $this->grouped_expense,
            'breakfast_expense' => (int) $this->breakfast_expense,
            'meal_expense' => (int) $this->meal_expense,
            'meal_rate' => round($this->meal_rate / 1_000_000, 4),
            'group_expense_per_person' => round($this->group_expense_per_person / 1_000_000, 4),
            'participants' => ParticipantResultResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
