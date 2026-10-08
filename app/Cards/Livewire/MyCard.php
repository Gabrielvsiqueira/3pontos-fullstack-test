<?php

declare(strict_types=1);

namespace App\Cards\Livewire;

use App\Models\Card;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::cardholder')]
#[Title('Meu cartão · Passa')]
final class MyCard extends Component
{
    public function render(): View
    {
        return view('cards.my-card', [
            'card' => $this->card(),
        ]);
    }

    private function card(): Card
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return Card::query()->with('user')->where('user_id', $user->id)->firstOr(fn () => abort(403));
    }
}
