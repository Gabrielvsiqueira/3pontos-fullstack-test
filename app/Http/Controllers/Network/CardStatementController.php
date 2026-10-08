<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StatementRequest;
use Illuminate\Http\JsonResponse;
use Passa\Ledger\CardStatement;
use Passa\Ledger\Models\Card;

final class CardStatementController extends Controller
{
    public function __invoke(StatementRequest $request, string $cardToken, CardStatement $statement): JsonResponse
    {
        $card = Card::query()->where('token', $cardToken)->firstOrFail();

        return response()->json($statement->for($card, $request->month()));
    }
}
