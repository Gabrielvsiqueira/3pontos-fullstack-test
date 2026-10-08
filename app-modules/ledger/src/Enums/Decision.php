<?php

declare(strict_types=1);

namespace Passa\Ledger\Enums;

enum Decision: string
{
    case Approved = 'approved';
    case Declined = 'declined';
}
