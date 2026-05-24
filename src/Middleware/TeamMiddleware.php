<?php

namespace Metrial\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $team = $request->route('team');

        if (! $team) {
            abort(403, 'No team context provided.');
        }

        if (! $user->isMemberOf($team)) {
            abort(403, 'You are not a member of this team.');
        }

        // Switch to this team context for downstream resolution
        $user->switchTeam($team);

        return $next($request);
    }
}
