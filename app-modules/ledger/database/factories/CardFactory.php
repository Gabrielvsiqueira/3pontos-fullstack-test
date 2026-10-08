<?php

declare(strict_types=1);

namespace Passa\Ledger\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;

/**
 * @extends Factory<Card>
 */
final class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => fn (array $attributes): int => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'token' => 'tok_'.Str::lower(Str::random(12)),
            'monthly_limit_cents' => 100_000,
            'purchase_limit_cents' => null,
            'blocked_mccs' => [],
            'blocked' => false,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'blocked' => true,
        ]);
    }
}
