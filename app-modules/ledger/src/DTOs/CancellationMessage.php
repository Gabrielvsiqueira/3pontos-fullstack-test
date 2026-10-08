<?php

declare(strict_types=1);

namespace Passa\Ledger\DTOs;

use Carbon\CarbonImmutable;

final readonly class CancellationMessage
{
    /**
     * @param  array<mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $authorizationId,
        public CarbonImmutable $occurredAt,
        public array $payload,
    ) {}
}
