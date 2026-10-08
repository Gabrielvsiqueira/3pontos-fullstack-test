<?php

declare(strict_types=1);

namespace Passa\Network\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Passa\Authorization\Actions\AuthorizePurchase;
use Passa\Network\Http\Requests\AuthorizationRequest;

final class AuthorizationController
{
    public function __invoke(AuthorizationRequest $request, AuthorizePurchase $authorize): JsonResponse
    {
        $result = $authorize->handle($request->message());

        return response()->json($result->toArray());
    }
}
