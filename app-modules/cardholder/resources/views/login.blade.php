<div class="m-auto w-full max-w-sm sm:max-w-md lg:max-w-lg">
    <div class="mb-6 text-center sm:mb-8">
        <h1 class="text-2xl font-semibold sm:text-3xl">Olá, entre no seu cartão</h1>
        <p class="mt-1 text-sm text-slate-500 sm:mt-2 sm:text-base dark:text-slate-400">Use o e-mail e a senha que o financeiro da sua empresa enviou.</p>
    </div>

    <form
        wire:submit="login"
        class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:space-y-5 sm:p-8 dark:border-slate-800 dark:bg-slate-900"
    >
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium sm:text-base">E-mail</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="username"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none sm:px-4 sm:py-3 sm:text-base dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-500"
            />
            @error ('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium sm:text-base">Senha</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none sm:px-4 sm:py-3 sm:text-base dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-500"
            />
            @error ('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-600/40 focus:outline-none disabled:opacity-60 sm:py-3 sm:text-lg"
        >
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando…</span>
        </button>
    </form>
</div>
