<div class="mx-auto max-w-sm">
    <h1 class="mb-6 text-2xl font-semibold">Olá, entre no seu cartão</h1>

    <form wire:submit="login" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <label for="email" class="mb-2 block text-sm font-medium">E-mail</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="username"
                required
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-emerald-800 focus:outline-none"
            />
            @error ('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Senha</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-emerald-700 focus:outline-none"
            />
            @error ('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-md bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando…</span>
        </button>
    </form>
</div>
