<?php

declare(strict_types=1);

namespace Passa\Network\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Passa\Ledger\Actions\RecordEvent;
use Passa\Ledger\Enums\EventOutcome;
use Passa\Network\Http\Requests\EventRequest;
use Symfony\Component\HttpFoundation\Response;

final class EventController
{
    public function __invoke(EventRequest $request, RecordEvent $recordEvent): JsonResponse
    {
        $outcome = $recordEvent->handle($request->message());

        return response()->json(['status' => $outcome->value], match ($outcome) {
            EventOutcome::Accepted => Response::HTTP_ACCEPTED,
            EventOutcome::AlreadyReceived => Response::HTTP_OK,
            EventOutcome::Conflict => Response::HTTP_CONFLICT,
        });
    }
}
