<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Passa\Ledger\BillingMonth;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;

final class CardAvailableController extends Controller
{
    public function __invoke(string $cardToken, Ledger $ledger): JsonResponse
    {
        $card = Card::query()->where('token', $cardToken)->firstOrFail();
        $company = Company::query()->findOrFail($card->company_id);
        $month = BillingMonth::of(now());

        return response()->json([
            'available_cents' => $ledger->availableFor($card, $company, $month),
            'limit_remaining_cents' => $ledger->limitRemaining($card, $month),
        ]);
    }
}
