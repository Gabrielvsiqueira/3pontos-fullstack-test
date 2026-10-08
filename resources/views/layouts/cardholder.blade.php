<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title ?? 'Passa' }}</title>
    @vite ('resources/css/app.css')
</head>
<body class="h-full font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-slate-100">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
            <span class="text-lg font-semibold text-emerald-700">Passa</span>

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-900 hover:text-slate-900">Sair</button>
                </form>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">{{ $slot }}</main>
</body>
</html>
