<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Decision;
use App\Enums\DeclineReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

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
