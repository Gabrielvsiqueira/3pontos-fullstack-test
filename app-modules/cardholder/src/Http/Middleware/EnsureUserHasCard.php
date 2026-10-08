<?php

declare(strict_types=1);

namespace Passa\Cardholder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Passa\Ledger\Models\Card;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasCard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || Card::query()->where('user_id', $user->getAuthIdentifier())->doesntExist(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
