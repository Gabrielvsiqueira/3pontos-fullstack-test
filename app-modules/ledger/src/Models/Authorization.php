<?php

declare(strict_types=1);

namespace Passa\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Passa\Ledger\Enums\Decision;
use Passa\Ledger\Enums\DeclineReason;

final class Authorization extends Model
{
    protected $fillable = [
        'purchase_id',
        'card_token',
        'amount_cents',
        'currency',
        'mcc',
        'merchant_name',
        'merchant_city',
        'merchant_country',
        'occurred_at',
        'decision',
        'reason',
        'payload',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return MorphOne<Transaction, $this>
     */
    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'source');
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'decision' => Decision::class,
            'reason' => DeclineReason::class,
            'payload' => 'array',
        ];
    }
}
