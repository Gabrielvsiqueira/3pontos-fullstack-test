<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardMonth extends Model
{
    protected $fillable = [
        'card_id',
        'month',
        'limit_delta_cents',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function casts(): array
    {
        return [
            'limit_delta_cents' => 'integer',
        ];
    }
}
