<?php

declare(strict_types=1);

namespace Passa\Authorization;

use Passa\Ledger\Enums\Decision;
use Passa\Ledger\Enums\DeclineReason;
use Passa\Ledger\Models\Authorization;

final readonly class AuthorizationResult
{
    public function __construct(
        public Decision $decision,
        public ?DeclineReason $reason = null,
    ) {}

    public static function from(Authorization $authorization): self
    {
        return new self($authorization->decision, $authorization->reason);
    }

    /**
     * @return array{decision: string, reason?: string}
     */
    public function toArray(): array
    {
        return $this->reason instanceof DeclineReason
            ? ['decision' => $this->decision->value, 'reason' => $this->reason->value]
            : ['decision' => $this->decision->value];
    }
}
