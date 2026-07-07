<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        /** @var Admin|null $admin */
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $normalizedRoles = collect($roles)
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter()
            ->values()
            ->all();

        abort_if($normalizedRoles === [], 403);
        abort_unless($admin->hasAnyRole($normalizedRoles), 403);

        return $next($request);
    }
}
