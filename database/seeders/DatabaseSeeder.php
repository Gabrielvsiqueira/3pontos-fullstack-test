<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * A gestora do painel já vem pronta. O restante do cadastro é seu.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'marina@acme.test'],
            ['name' => 'Marina', 'password' => 'password'],
        );
    }
}
