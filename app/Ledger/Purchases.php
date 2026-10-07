<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Models\Purchase;

final readonly class Purchases
{
    public function lockOrCreate(string $networkAuthorizationId): Purchase
    {
        Purchase::query()->insertOrIgnore([
            'network_authorization_id' => $networkAuthorizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Purchase::query()
            ->where('network_authorization_id', $networkAuthorizationId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
