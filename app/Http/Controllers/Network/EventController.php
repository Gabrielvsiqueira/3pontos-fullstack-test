<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\EventRequest;
use Illuminate\Http\JsonResponse;
use Passa\Ledger\Actions\RecordEvent;

final class EventController extends Controller
{
    public function __invoke(EventRequest $request, RecordEvent $recordEvent): JsonResponse
    {
        $outcome = $recordEvent->handle($request->message());

        return response()->json(['status' => $outcome->value], $outcome->httpStatus());
    }
}
