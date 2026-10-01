<?php

namespace App\Http\Resources;

use App\Models\ParticipantResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParticipantResult
 */
class ParticipantResultResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'breakfast_count' => (int) $this->breakfast_count,
            'meal_count' => (int) $this->meal_count,
            'total_meals' => (int) ($this->breakfast_count + $this->meal_count),
            'breakfast_expense' => (int) $this->breakfast_expense,
            'meal_expense' => (int) $this->meal_expense,
            'group_expense' => (int) $this->group_expense,
            'total_expense' => (int) $this->total_expense,
            'total_contribution' => (int) $this->total_contribution,
            'adjustment' => (int) $this->adjustment,
            'status' => $this->adjustment > 0 ? 'owes' : ($this->adjustment < 0 ? 'refund' : 'settled'),
        ];
    }
}
