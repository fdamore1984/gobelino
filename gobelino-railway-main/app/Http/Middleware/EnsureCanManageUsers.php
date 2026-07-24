<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canManageUsers()) {
            abort(403, 'Non hai i permessi per gestire gli utenti.');
        }

        return $next($request);
    }
}
