<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Undergrace\Mbc\Models\MbcTurn
 */
class TurnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'turn_number' => $this->turn_number,
            'type' => $this->type->value,
            'content' => $this->content,
            'tool_calls' => $this->tool_calls,
            'tool_results' => $this->tool_results,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'stop_reason' => $this->stop_reason,
            'duration_ms' => $this->duration_ms,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
