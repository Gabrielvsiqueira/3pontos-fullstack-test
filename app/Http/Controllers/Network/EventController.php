<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\EventRequest;
use App\Ledger\Actions\RecordEvent;
use Illuminate\Http\JsonResponse;

final class EventController extends Controller
{
    public function __invoke(EventRequest $request, RecordEvent $recordEvent): JsonResponse
    {
        $outcome = $recordEvent->handle($request->message());

        return response()->json(['status' => $outcome->value], $outcome->httpStatus());
    }
}
