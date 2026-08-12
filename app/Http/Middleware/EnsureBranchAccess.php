<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = app(BranchContext::class)->id();
        abort_unless($branchId !== null, 403, 'Your account is not assigned to an active branch.');
        session(['current_branch_id' => $branchId]);

        return $next($request);
    }
}
