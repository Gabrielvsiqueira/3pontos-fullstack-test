<?php

declare(strict_types=1);

namespace App\Network\Messages;

use Carbon\CarbonImmutable;

final readonly class CaptureMessage
{
    /**
     * @param  array<mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $authorizationId,
        public CarbonImmutable $occurredAt,
        public int $amountCents,
        public string $currency,
        public int $sequence,
        public bool $final,
        public array $payload,
    ) {}
}
