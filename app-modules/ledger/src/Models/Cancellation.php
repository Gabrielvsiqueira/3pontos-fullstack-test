<?php

declare(strict_types=1);

namespace Passa\Ledger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

final class Cancellation extends Model
{
    protected $fillable = [
        'network_id',
        'purchase_id',
        'occurred_at',
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
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }
}
