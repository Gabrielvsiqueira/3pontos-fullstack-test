<?php

declare(strict_types=1);

namespace Passa\Network\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Passa\Network\NetworkSignature;
use Symfony\Component\HttpFoundation\Response;

final class VerifyNetworkSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $valid = NetworkSignature::fromConfig()->verify(
            $request->header('X-Network-Timestamp'),
            $request->header('X-Network-Signature'),
            $request->getContent(),
        );

        if (! $valid) {
            return response()->json(['message' => 'Invalid network signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
