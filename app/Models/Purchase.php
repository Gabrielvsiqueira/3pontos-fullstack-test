<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Purchase extends Model
{
    protected $fillable = [
        'network_authorization_id',
        'card_id',
        'month',
        'authorized_cents',
        'captured_cents',
        'held_cents',
        'closed',
        'over_capture',
        'over_purchase_limit',
        'captured_when_declined',
        'captured_after_cancellation',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function authorization(): HasOne
    {
        return $this->hasOne(Authorization::class);
    }

    public function captures(): HasMany
    {
        return $this->hasMany(Capture::class);
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(Cancellation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isFlagged(): bool
    {
        return $this->over_capture
            || $this->over_purchase_limit
            || $this->captured_when_declined
            || $this->captured_after_cancellation;
    }

    protected function casts(): array
    {
        return [
            'authorized_cents' => 'integer',
            'captured_cents' => 'integer',
            'held_cents' => 'integer',
            'closed' => 'boolean',
            'over_capture' => 'boolean',
            'over_purchase_limit' => 'boolean',
            'captured_when_declined' => 'boolean',
            'captured_after_cancellation' => 'boolean',
        ];
    }
}
