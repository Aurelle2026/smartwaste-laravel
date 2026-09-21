<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            return response()->json(['message' => 'Accès non autorisé pour ce profil.'], 403);
        }

        return $next($request);
    }
}
