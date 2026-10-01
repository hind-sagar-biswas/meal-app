<?php

namespace App\Http\Resources;

use App\Models\Month;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Month
 */
class MonthResource extends JsonResource
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
            'year' => (int) $this->year,
            'month' => (int) $this->month,
            'is_closed' => (bool) $this->is_closed,
            'breakfast_price' => (int) $this->breakfast_price,
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by' => $this->whenLoaded('closedBy', fn () => [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
