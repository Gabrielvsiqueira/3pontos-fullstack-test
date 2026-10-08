<?php

declare(strict_types=1);

namespace Passa\Ledger\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Passa\Ledger\Models\Company;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }
}
