<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Undergrace\Mbc\Http\Resources\TurnCollection;
use Undergrace\Mbc\Models\MbcSession;

class TurnController extends Controller
{
    /**
     * GET /mbc/sessions/{uuid}/turns
     *
     * Paginated timeline of turns for a specific session.
     */
    public function index(Request $request, string $uuid): TurnCollection
    {
        $session = MbcSession::where('uuid', $uuid)->firstOrFail();

        $turns = $session->turns()
            ->orderBy('turn_number')
            ->paginate($request->integer('per_page', 50));

        return new TurnCollection($turns);
    }
}
