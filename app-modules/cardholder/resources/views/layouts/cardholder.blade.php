<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title ?? 'Passa' }}</title>
    <script>
        try {
            const theme = localStorage.getItem('passa-theme');
            const dark = theme === 'dark' || (theme === null && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        } catch {}
    </script>
    @vite ('resources/css/app.css')
</head>
<body
    class="flex min-h-full flex-col bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
>
    <header class="border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
            <span class="flex items-center gap-2 text-lg font-semibold text-emerald-700 dark:text-emerald-400">
                <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-600 text-sm text-white"
                    >P</span
                >
                Passa
            </span>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    data-test="theme-toggle"
                    aria-label="Alternar tema claro e escuro"
                    onclick="
                        const dark = document.documentElement.classList.toggle('dark');
                        try {
                            localStorage.setItem('passa-theme', dark ? 'dark' : 'light');
                        } catch {}
                    "
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                >
                    <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.27-2.6.75-3.75A9.75 9.75 0 0 0 2.25 12 9.75 9.75 0 0 0 12 21.75 9.75 9.75 0 0 0 21.75 15Z" />
                    </svg>
                    <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.36.39-1.59 1.59M21 12h-2.25m-.39 6.36-1.59-1.59M12 18.75V21m-4.77-4.23-1.59 1.59M5.25 12H3m4.23-4.77L5.64 5.64M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                </button>

                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                        >
                            Sair
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col px-4 py-8">{{ $slot }}</main>
</body>
</html>
