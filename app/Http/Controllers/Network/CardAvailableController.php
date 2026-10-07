<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CardAvailableController extends Controller
{
    public function __invoke(string $cardToken): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], Response::HTTP_NOT_IMPLEMENTED);
    }
}
