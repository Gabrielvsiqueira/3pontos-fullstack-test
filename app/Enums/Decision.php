<?php

declare(strict_types=1);

namespace App\Enums;

enum Decision: string
{
    case Approved = 'approved';
    case Declined = 'declined';
}
