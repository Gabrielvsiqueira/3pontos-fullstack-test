<?php

declare(strict_types=1);

namespace App\Enums;

use Symfony\Component\HttpFoundation\Response;

enum EventOutcome: string
{
    case Accepted = 'accepted';
    case AlreadyReceived = 'already_received';
    case Conflict = 'conflict';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Accepted => Response::HTTP_ACCEPTED,
            self::AlreadyReceived => Response::HTTP_OK,
            self::Conflict => Response::HTTP_CONFLICT,
        };
    }
}
