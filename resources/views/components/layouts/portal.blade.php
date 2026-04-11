<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'VTB Taxi' }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <div class="fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.22),_transparent_32%),radial-gradient(circle_at_top_right,_rgba(249,115,22,0.18),_transparent_28%),linear-gradient(160deg,_#020617_0%,_#0f172a_55%,_#111827_100%)]"></div>
            <div class="absolute left-1/2 top-0 h-96 w-96 -translate-x-1/2 rounded-full bg-cyan-400/10 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/6 p-6 shadow-2xl shadow-black/20 backdrop-blur">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">VTB Taxi</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $heading ?? 'Панель управления' }}</h1>
                        @isset($subheading)
                            <p class="mt-2 max-w-3xl text-sm text-slate-300">{{ $subheading }}</p>
                        @endisset
                    </div>

                    @isset($currentUser)
                        <div class="rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3 text-sm">
                            <p class="text-slate-400">Текущий пользователь</p>
                            <p class="mt-1 font-semibold text-white">{{ $currentUser->full_name }}</p>
                            <p class="text-slate-400">{{ $currentUser->employee_number }} · {{ $currentUser->role->name }}</p>
                        </div>
                    @endisset
                </div>

                <nav class="flex flex-wrap gap-3 text-sm">
                    <a class="rounded-full border border-white/10 px-4 py-2 text-slate-200 transition hover:border-cyan-300/50 hover:bg-cyan-400/10" href="{{ route('home') }}">Главная</a>
                    @isset($currentUser)
                        <a class="rounded-full border border-white/10 px-4 py-2 text-slate-200 transition hover:border-cyan-300/50 hover:bg-cyan-400/10" href="{{ route('employee.requests.index') }}">Кабинет сотрудника</a>
                        @if (in_array($currentUser->role->name, ['Manager', 'Admin'], true))
                            <a class="rounded-full border border-white/10 px-4 py-2 text-slate-200 transition hover:border-amber-300/50 hover:bg-amber-400/10" href="{{ route('manager.requests.index') }}">Кабинет руководителя</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="inline-flex">
                            @csrf
                            <button class="rounded-full border border-white/10 px-4 py-2 text-slate-200 transition hover:border-rose-300/50 hover:bg-rose-500/10" type="submit">Выйти</button>
                        </form>
                    @else
                        <a class="rounded-full border border-white/10 px-4 py-2 text-slate-200 transition hover:border-cyan-300/50 hover:bg-cyan-400/10" href="{{ route('login') }}">Войти</a>
                    @endisset
                </nav>
            </header>

            @if (session('status'))
                <div class="mb-6 rounded-3xl border border-emerald-400/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-3xl border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-100">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </body>
</html>
