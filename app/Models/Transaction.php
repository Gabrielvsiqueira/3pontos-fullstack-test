<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class Transaction extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'card_id',
        'purchase_id',
        'month',
        'type',
        'reference',
        'occurred_at',
        'limit_delta_cents',
        'balance_delta_cents',
        'held_delta_cents',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Transactions are append-only.'));
        self::deleting(static fn (): never => throw new LogicException('Transactions are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'limit_delta_cents' => 'integer',
            'balance_delta_cents' => 'integer',
            'held_delta_cents' => 'integer',
        ];
    }
}
