<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StatementRequest;
use App\Ledger\CardStatement;
use App\Models\Card;
use Illuminate\Http\JsonResponse;

final class CardStatementController extends Controller
{
    public function __invoke(StatementRequest $request, string $cardToken, CardStatement $statement): JsonResponse
    {
        $card = Card::query()->where('token', $cardToken)->firstOrFail();

        return response()->json($statement->for($card, $request->month()));
    }
}
