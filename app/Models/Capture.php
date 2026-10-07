<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

final class Capture extends Model
{
    protected $fillable = [
        'network_id',
        'purchase_id',
        'sequence',
        'amount_cents',
        'currency',
        'final',
        'occurred_at',
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
            'sequence' => 'integer',
            'amount_cents' => 'integer',
            'final' => 'boolean',
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }
}
