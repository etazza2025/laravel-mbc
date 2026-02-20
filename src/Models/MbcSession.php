<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Undergrace\Mbc\Enums\SessionStatus;

class MbcSession extends Model
{
    protected $table = 'mbc_sessions';

    protected $fillable = [
        'uuid',
        'name',
        'status',
        'model',
        'system_prompt',
        'context',
        'config',
        'total_turns',
        'total_input_tokens',
        'total_output_tokens',
        'estimated_cost_usd',
        'result',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'context' => 'array',
            'config' => 'array',
            'result' => 'array',
            'estimated_cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function turns(): HasMany
    {
        return $this->hasMany(MbcTurn::class, 'session_id')->orderBy('turn_number');
    }

    // ── Scopes ──

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::PENDING);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::FAILED);
    }

    // ── Helpers ──

    public function isRunning(): bool
    {
        return $this->status === SessionStatus::RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === SessionStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === SessionStatus::FAILED;
    }

    public function durationInSeconds(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
