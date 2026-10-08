<?php

declare(strict_types=1);

namespace App\Cards\Livewire;

use App\Ledger\BillingMonth;
use App\Ledger\CardStatement;
use App\Ledger\Ledger;
use App\Ledger\PurchaseHistory;
use App\Models\Card;
use App\Models\Purchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::cardholder')]
#[Title('Meu cartão · Passa')]
final class MyCard extends Component
{
    use WithPagination;

    public const int PER_PAGE = 5;

    public const int POLL_SECONDS = 4;

    public function render(Ledger $ledger, CardStatement $statements): View
    {
        $card = $this->card();
        $month = BillingMonth::of(now());
        $purchases = $this->purchases($card);

        return view('cards.my-card', [
            'card' => $card,
            'monthLabel' => $this->monthLabel($month),
            'available' => $ledger->availableFor($card, $card->company, $month),
            'limitRemaining' => $ledger->limitRemaining($card, $month),
            'statement' => $statements->for($card, $month),
            'purchases' => $purchases,
            'histories' => collect($purchases->items())
                ->mapWithKeys(fn (Purchase $purchase): array => [$purchase->id => PurchaseHistory::of($purchase)])
                ->all(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Purchase>
     */
    private function purchases(Card $card): LengthAwarePaginator
    {
        return $card->purchases()
            ->with(['authorization', 'captures', 'cancellation'])
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    private function monthLabel(string $month): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m', $month, BillingMonth::TIMEZONE);

        return $date instanceof CarbonImmutable ? $date->locale('pt_BR')->translatedFormat('F \d\e Y') : $month;
    }

    private function card(): Card
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return Card::query()->with(['user', 'company'])->where('user_id', $user->id)->firstOr(fn () => abort(403));
    }
}
