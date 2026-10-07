<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function availableCents(): int
    {
        return $this->balance_cents - $this->held_cents;
    }

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'held_cents' => 'integer',
        ];
    }
}
