<?php

declare(strict_types=1);

namespace Passa\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

final class Deposit extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'amount_cents',
        'occurred_at',
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
        ];
    }
}
