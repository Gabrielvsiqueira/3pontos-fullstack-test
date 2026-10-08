<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Passa\Ledger\Actions\RecordDeposit;
use Passa\Ledger\Models\Company;

final class DatabaseSeeder extends Seeder
{
    public function run(RecordDeposit $recordDeposit): void
    {
        $acme = Company::query()->firstOrCreate(['name' => 'Acme']);

        User::query()->updateOrCreate(
            ['email' => 'marina@acme.test'],
            ['name' => 'Marina', 'password' => 'password', 'company_id' => $acme->id, 'role' => UserRole::Manager],
        );

        $cards = [
            ['Ana', 'tok_ana', 200_000, 80_000, ['7995'], false],
            ['Bruno', 'tok_bruno', 50_000, null, [], false],
            ['Carla', 'tok_carla', 100_000, null, [], true],
            ['Diego', 'tok_diego', 5_000_000, null, [], false],
        ];

        foreach ($cards as [$name, $token, $monthlyLimit, $purchaseLimit, $blockedMccs, $blocked]) {
            $holder = User::query()->updateOrCreate(
                ['email' => mb_strtolower($name).'@acme.test'],
                ['name' => $name, 'password' => 'password', 'company_id' => $acme->id, 'role' => UserRole::Cardholder],
            );

            $acme->cards()->updateOrCreate(
                ['token' => $token],
                [
                    'user_id' => $holder->id,
                    'monthly_limit_cents' => $monthlyLimit,
                    'purchase_limit_cents' => $purchaseLimit,
                    'blocked_mccs' => $blockedMccs,
                    'blocked' => $blocked,
                ],
            );
        }

        if ($acme->deposits()->doesntExist()) {
            $recordDeposit->handle($acme, 1_000_000);
        }
    }
}
