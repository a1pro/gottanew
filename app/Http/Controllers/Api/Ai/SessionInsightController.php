<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Api\BaseController;
use App\Models\Session\CoachingSession;
use App\Services\Ai\SessionInsightService;
use Illuminate\Http\Request;

class SessionInsightController extends BaseController
{
    public function __construct(private SessionInsightService $insightService)
    {
    }

    public function show(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        return $this->success(
            $this->insightService->payload($session)
        );
    }

    public function generatePre(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $this->insightService->generatePreSessionSummary($session, true);

        return $this->success(
            $this->insightService->payload($session),
            'Pre-session summary generated'
        );
    }

    public function generatePost(Request $request, $id)
    {
        $session = $this->getAuthorizedSession($request, $id);

        $this->insightService->generatePostSessionSummary($session, true);

        return $this->success(
            $this->insightService->payload($session),
            'Post-session summary generated'
        );
    }

    private function getAuthorizedSession(Request $request, $id): CoachingSession
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $session = CoachingSession::with(['client', 'coach', 'recording'])->findOrFail($id);

        $isClient = (int) $session->client_id === (int) $user->id;
        $isCoach = (int) optional($session->coach)->user_id === (int) $user->id;
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

        abort_unless($isClient || $isCoach || $isAdmin, 403, 'Unauthorized');

        return $session;
    }
}