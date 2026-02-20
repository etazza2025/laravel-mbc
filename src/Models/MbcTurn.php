<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Undergrace\Mbc\Enums\TurnType;

class MbcTurn extends Model
{
    public $timestamps = false;

    protected $table = 'mbc_turns';

    protected $fillable = [
        'session_id',
        'turn_number',
        'type',
        'content',
        'tool_calls',
        'tool_results',
        'input_tokens',
        'output_tokens',
        'stop_reason',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TurnType::class,
            'content' => 'array',
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function session(): BelongsTo
    {
        return $this->belongsTo(MbcSession::class, 'session_id');
    }

    // ── Helpers ──

    public function isAssistant(): bool
    {
        return $this->type === TurnType::ASSISTANT;
    }

    public function isToolResult(): bool
    {
        return $this->type === TurnType::TOOL_RESULT;
    }

    public function hasToolCalls(): bool
    {
        return ! empty($this->tool_calls);
    }
}
