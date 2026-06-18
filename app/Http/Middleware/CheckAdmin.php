<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $roleName = strtolower(optional(optional($user)->role)->name ?? '');

        if ($roleName !== 'admin') {
            return response()->json([
                'message' => 'Accès refusé. Permissions administrateur requises.',
            ], 403);
        }

        return $next($request);
    }
}
