<?php

declare(strict_types=1);

namespace App\Network;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class NetworkSignature
{
    public function __construct(
        #[SensitiveParameter] private string $secret,
        private int $toleranceSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.network.secret'),
            (int) config('services.network.tolerance'),
        );
    }

    public function sign(string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
    }

    public function verify(?string $timestamp, ?string $signature, string $body): bool
    {
        if ($this->secret === '' || $timestamp === null || $signature === null) {
            return false;
        }

        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1) {
            return false;
        }

        if (abs(CarbonImmutable::now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        return hash_equals($this->sign($timestamp, $body), $signature);
    }
}
