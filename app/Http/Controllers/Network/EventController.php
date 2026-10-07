<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\EventRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class EventController extends Controller
{
    public function __invoke(EventRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], Response::HTTP_NOT_IMPLEMENTED);
    }
}
