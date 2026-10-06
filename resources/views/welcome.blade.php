<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
        <main class="mx-auto flex min-h-screen max-w-2xl flex-col items-start justify-center gap-6 px-6">
            <p class="text-sm font-medium uppercase tracking-widest text-emerald-400">Desafio Full Stack · 3Pontos Tech</p>
            <h1 class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="text-lg text-neutral-400">
                O enunciado está no <code class="rounded bg-neutral-900 px-1.5 py-0.5 text-neutral-200">README.md</code>.
                O painel da Acme fica em
                <a href="{{ url('/admin') }}" class="text-emerald-400 underline decoration-emerald-400/40 underline-offset-4 hover:decoration-emerald-400">/admin</a>.
            </p>
        </main>
    </body>
</html>
