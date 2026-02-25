<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Undergrace\Mbc\Models\MbcSession
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status->value,
            'model' => $this->model,
            'total_turns' => $this->total_turns,
            'total_input_tokens' => $this->total_input_tokens,
            'total_output_tokens' => $this->total_output_tokens,
            'estimated_cost_usd' => $this->estimated_cost_usd,
            'result' => $this->result,
            'has_error' => $this->error !== null,
            'duration_seconds' => $this->durationInSeconds(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'turns' => TurnResource::collection($this->whenLoaded('turns')),
        ];
    }
}
