<?php

declare(strict_types=1);

namespace Passa\Authorization\DTOs;

use Carbon\CarbonImmutable;

final readonly class AuthorizationMessage
{
    /**
     * @param  array<mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $cardToken,
        public int $amountCents,
        public string $currency,
        public string $mcc,
        public string $merchantName,
        public string $merchantCity,
        public string $merchantCountry,
        public CarbonImmutable $occurredAt,
        public array $payload,
    ) {}
}
