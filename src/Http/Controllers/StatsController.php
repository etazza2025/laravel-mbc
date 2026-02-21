<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Http\Resources\SessionCollection;
use Undergrace\Mbc\Models\MbcSession;

class StatsController extends Controller
{
    /**
     * GET /mbc/stats
     *
     * Aggregate statistics: counts by status, total tokens, total cost.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MbcSession::query();

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        $statusCounts = [];

        foreach (SessionStatus::cases() as $status) {
            $statusCounts[$status->value] = (clone $query)
                ->where('status', $status)
                ->count();
        }

        return response()->json([
            'data' => [
                'total_sessions' => (clone $query)->count(),
                'sessions_by_status' => $statusCounts,
                'total_turns' => (int) (clone $query)->sum('total_turns'),
                'total_input_tokens' => (int) (clone $query)->sum('total_input_tokens'),
                'total_output_tokens' => (int) (clone $query)->sum('total_output_tokens'),
                'total_tokens' => (int) (clone $query)->sum('total_input_tokens')
                    + (int) (clone $query)->sum('total_output_tokens'),
                'total_estimated_cost_usd' => round(
                    (float) (clone $query)->sum('estimated_cost_usd'), 6
                ),
                'avg_turns_per_session' => round(
                    (float) (clone $query)->avg('total_turns'), 1
                ),
                'avg_cost_per_session' => round(
                    (float) (clone $query)->avg('estimated_cost_usd'), 6
                ),
            ],
        ]);
    }

    /**
     * GET /mbc/agents/active
     *
     * Currently running or pending sessions.
     */
    public function active(): SessionCollection
    {
        $sessions = MbcSession::query()
            ->whereIn('status', [SessionStatus::RUNNING, SessionStatus::PENDING])
            ->latest()
            ->get();

        return new SessionCollection($sessions);
    }
}
