<?php

declare(strict_types=1);

namespace Passa\Ledger\Enums;

enum EventOutcome: string
{
    case Accepted = 'accepted';
    case AlreadyReceived = 'already_received';
    case Conflict = 'conflict';
}
