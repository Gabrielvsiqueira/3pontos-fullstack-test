@use ('Passa\Cardholder\Livewire\MyCard')
@use ('Passa\Ledger\Enums\Decision')
@use ('Passa\Ledger\BillingMonth')
@use ('App\Support\Money')
@use ('Carbon\CarbonImmutable')

<div wire:poll.{{ MyCard::POLL_SECONDS }}s class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Olá, {{ $card->user->name }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Cartão {{ $card->token }}</p>
        </div>

        <p class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="relative flex size-2">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
            </span>
            <span wire:loading.remove>Atualiza sozinho a cada {{ MyCard::POLL_SECONDS }} segundos</span>
            <span wire:loading>Atualizando…</span>
        </p>
    </div>

    @if ($card->blocked)
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300"
        >
            Este cartão está bloqueado. Novas compras serão recusadas. Fale com o financeiro da {{ $card->company->name }}.
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-600 dark:text-slate-400">Disponível para comprar agora</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-700 tabular-nums dark:text-emerald-400" data-test="available">{{ Money::format($available) }}</p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">O menor valor entre o limite restante e o saldo da {{ $card->company->name }}.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-600 dark:text-slate-400">Limite restante em {{ $monthLabel }}</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-700 tabular-nums dark:text-emerald-400" data-test="limit-remaining">{{ Money::format($limitRemaining) }}</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full bg-emerald-500"
                    style="width: {{ $card->monthly_limit_cents > 0 ? max(0, min(100, intdiv($limitRemaining * 100, $card->monthly_limit_cents))) : 0 }}%"
                ></div>
            </div>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">de {{ Money::format($card->monthly_limit_cents) }} no mês</p>
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Statement de {{ $monthLabel }}</h2>

        @if ($statement['transactions'] === [])
            <div
                class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
            >
                Nenhuma transaction em {{ $monthLabel }}. Quando você usar o cartão, ela aparece aqui.
            </div>
        @else
            <div
                class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-800/50 dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Data</th>
                            <th class="px-4 py-2 font-medium">Tipo</th>
                            <th class="px-4 py-2 font-medium">Referência</th>
                            <th class="px-4 py-2 text-right font-medium">Valor</th>
                            <th class="px-4 py-2 text-right font-medium">Limite restante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($statement['transactions'] as $line)
                            <tr wire:key="statement-{{ $line['reference'] }}-{{ $loop->index }}">
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600 dark:text-slate-400">
                                    {{ CarbonImmutable::parse($line['occurred_at'])->setTimezone(BillingMonth::TIMEZONE)->format('d/m H:i') }}
                                </td>
                                <td class="px-4 py-2">{{ $line['type'] }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-500 dark:text-slate-400">
                                    {{ $line['reference'] }}
                                </td>
                                <td
                                    @class (['px-4 py-2 text-right whitespace-nowrap tabular-nums', 'text-red-700 dark:text-red-400' => $line['amount_cents'] < 0, 'text-emerald-700 dark:text-emerald-400' => $line['amount_cents'] > 0])
                                >
                                    {{ Money::format($line['amount_cents']) }}
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap tabular-nums">
                                    {{ Money::format($line['limit_remaining_after_cents']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Minhas compras</h2>

        @if ($purchases->isEmpty())
            <div
                class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
            >
                Nenhuma compra ainda registrada. Assim que a rede pedir uma autorização, ela aparece aqui.
            </div>
        @else
            <ul
                class="space-y-4 transition-opacity"
                wire:loading.class="opacity-50"
                wire:target="gotoPage, nextPage, previousPage"
            >
                @foreach ($purchases as $purchase)
                    @php ($authorization = $purchase->authorization)
                    <li
                        wire:key="purchase-{{ $purchase->id }}"
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $authorization->merchant_name ?? 'Compra' }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $authorization?->occurred_at->setTimezone(BillingMonth::TIMEZONE)->format('d/m/Y H:i') }} ·
                                    MCC {{ $authorization->mcc ?? '—' }} ·
                                    <span class="font-mono">{{ $purchase->network_authorization_id }}</span>
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-semibold tabular-nums">{{ Money::format($authorization->amount_cents ?? 0) }}</p>
                                @if ($authorization?->decision === Decision::Approved)
                                    <span
                                        class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                                        >Aprovado</span
                                    >
                                @elseif ($authorization !== null)
                                    <span
                                        class="inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-500/15 dark:text-red-300"
                                    >
                                        declined · {{ $authorization->reason?->value }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400">Capturado</dt>
                                <dd class="tabular-nums">{{ Money::format($purchase->captured_cents) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400">Reservado</dt>
                                <dd class="tabular-nums">{{ Money::format($purchase->held_cents) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400">Situação</dt>
                                <dd>{{ $purchase->closed ? 'Encerrada' : 'Em aberto' }}</dd>
                            </div>
                        </dl>

                        <ol class="mt-4 space-y-2 border-l-2 border-slate-200 pl-4 dark:border-slate-800">
                            @foreach ($histories[$purchase->id] as $entry)
                                <li
                                    class="text-sm"
                                    wire:key="history-{{ $entry['reference'] }}-{{ $entry['message'] }}"
                                >
                                    <span
                                        class="text-xs whitespace-nowrap text-slate-500 tabular-nums dark:text-slate-400"
                                    >
                                        {{ $entry['occurred_at']->setTimezone(BillingMonth::TIMEZONE)->format('d/m H:i:s') }}
                                    </span>
                                    <span
                                        class="mx-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                        >{{ $entry['message'] }}</span
                                    >
                                    <span class="text-slate-700 dark:text-slate-300">{{ $entry['details'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ul>

            <div>{{ $purchases->links() }}</div>
        @endif
    </section>
</div>
