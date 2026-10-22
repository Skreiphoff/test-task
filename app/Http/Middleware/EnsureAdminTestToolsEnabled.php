<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checked on every request, so the gate survives route caching. See `app.admin_test_tools`. */
class EnsureAdminTestToolsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.admin_test_tools'), 404);

        return $next($request);
    }
}
