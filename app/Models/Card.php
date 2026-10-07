<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Card extends Model
{
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(CardMonth::class);
    }

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
