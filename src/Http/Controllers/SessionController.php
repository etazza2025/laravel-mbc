<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Undergrace\Mbc\Enums\SessionStatus;
use Undergrace\Mbc\Http\Resources\SessionCollection;
use Undergrace\Mbc\Http\Resources\SessionResource;
use Undergrace\Mbc\Models\MbcSession;

class SessionController extends Controller
{
    /**
     * GET /mbc/sessions
     *
     * List sessions with optional filters: status, from, to, name, model.
     */
    public function index(Request $request): SessionCollection
    {
        $query = MbcSession::query()->latest();

        if ($request->filled('status')) {
            $status = SessionStatus::tryFrom($request->string('status')->toString());

            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->string('name') . '%');
        }

        if ($request->filled('model')) {
            $query->where('model', $request->string('model')->toString());
        }

        return new SessionCollection(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * GET /mbc/sessions/{uuid}
     *
     * Session detail with turns eager-loaded.
     */
    public function show(string $uuid): SessionResource
    {
        $session = MbcSession::where('uuid', $uuid)
            ->with('turns')
            ->firstOrFail();

        return new SessionResource($session);
    }
}
