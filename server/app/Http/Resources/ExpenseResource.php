<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
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
            'date' => $this->date ? (is_string($this->date) ? $this->date : $this->date->toDateString()) : null,
            'cause' => $this->cause,
            'note' => $this->note,
            'is_grouped' => (bool) $this->is_grouped,
            'amount' => (int) $this->amount,
            'user' => [
                'id' => $this->user?->id ?? $this->user_id,
                'name' => $this->user?->name,
            ],
            'contributions' => ExpenseContributionResource::collection($this->whenLoaded('contributions')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
