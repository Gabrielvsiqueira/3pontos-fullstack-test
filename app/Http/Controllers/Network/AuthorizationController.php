<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\AuthorizationRequest;
use Illuminate\Http\JsonResponse;
use Passa\Authorization\Actions\AuthorizePurchase;

final class AuthorizationController extends Controller
{
    public function __invoke(AuthorizationRequest $request, AuthorizePurchase $authorize): JsonResponse
    {
        $result = $authorize->handle($request->message());

        return response()->json($result->toArray());
    }
}
