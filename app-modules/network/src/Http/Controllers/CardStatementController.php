<?php

declare(strict_types=1);

namespace Passa\Network\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Passa\Ledger\CardStatement;
use Passa\Ledger\Models\Card;
use Passa\Network\Http\Requests\StatementRequest;

final class CardStatementController
{
    public function __invoke(StatementRequest $request, string $cardToken, CardStatement $statement): JsonResponse
    {
        $card = Card::query()->where('token', $cardToken)->firstOrFail();

        return response()->json($statement->for($card, $request->month()));
    }
}
