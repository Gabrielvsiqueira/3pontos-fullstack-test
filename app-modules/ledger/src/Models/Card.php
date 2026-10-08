<?php

declare(strict_types=1);

namespace Passa\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Passa\Ledger\Database\Factories\CardFactory;

#[UseFactory(CardFactory::class)]
final class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'token',
        'monthly_limit_cents',
        'purchase_limit_cents',
        'blocked_mccs',
        'blocked',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * @return HasMany<CardMonth, $this>
     */
    public function months(): HasMany
    {
        return $this->hasMany(CardMonth::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    protected function casts(): array
    {
        return [
            'monthly_limit_cents' => 'integer',
            'purchase_limit_cents' => 'integer',
            'blocked_mccs' => 'array',
            'blocked' => 'boolean',
        ];
    }
}
