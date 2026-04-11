@props([
    'title' => 'VTB Taxi',
    'heading' => 'VTB Taxi',
    'subheading' => null,
    'currentUser' => null,
])

@php
    $roleLabels = [
        'Employee' => 'Сотрудник',
        'Manager' => 'Руководитель',
        'Admin' => 'Администратор',
    ];

    $currentRoute = request()->route()?->getName();
@endphp

<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <script>
            (() => {
                const savedTheme = localStorage.getItem('vtb-theme');
                document.documentElement.dataset.theme = savedTheme === 'light' ? 'light' : 'dark';
            })();
        </script>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800|space-grotesk:500,700" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('theme.css') }}">
        <link rel="stylesheet" href="{{ asset('theme-vtb.css') }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <script defer src="{{ asset('theme-vtb.js') }}"></script>
    </head>
    <body class="theme-body">
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>
        <div class="ambient ambient-grid"></div>

        <div class="shell">
            <header class="topbar" data-reveal>
                <div class="brand-lockup">
                    <a href="{{ route('home') }}" class="brand-mark" aria-label="VTB Taxi">
                        <span>VTB</span>
                    </a>
                    <div>
                        <p class="eyebrow">Корпоративное такси</p>
                        <h1>{{ $heading }}</h1>
                        @if ($subheading)
                            <p class="lede">{{ $subheading }}</p>
                        @endif
                    </div>
                </div>

                <div class="topbar-actions">
                    @if ($currentUser)
                        <div class="identity-chip">
                            <span class="identity-label">Аккаунт</span>
                            <strong>{{ $currentUser->full_name }}</strong>
                            <span>{{ $currentUser->employee_number }} · {{ $roleLabels[$currentUser->role->name] ?? $currentUser->role->name }}</span>
                        </div>
                    @endif
                </div>
            </header>

            <nav class="nav-pills" data-reveal>
                <a href="{{ route('home') }}" class="{{ $currentRoute === 'home' ? 'is-active' : '' }}">Главная</a>
                @if ($currentUser)
                    <a href="{{ route('employee.requests.index') }}" class="{{ str_starts_with($currentRoute ?? '', 'employee.') ? 'is-active' : '' }}">Мои заявки</a>
                    @if (in_array($currentUser->role->name, ['Manager', 'Admin'], true))
                        <a href="{{ route('manager.requests.index') }}" class="{{ str_starts_with($currentRoute ?? '', 'manager.') ? 'is-active' : '' }}">Панель руководителя</a>
                    @endif
                @endif

                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Переключить тему">
                    <span class="theme-toggle__dot"></span>
                    <span class="theme-toggle__label" data-theme-label>Светлая тема</span>
                </button>

                @if ($currentUser)
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-ghost">Выйти</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="{{ str_starts_with($currentRoute ?? '', 'login') ? 'is-active' : '' }}">Войти</a>
                @endif
            </nav>

            @if (session('status'))
                <div class="flash flash-success" data-reveal>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="flash flash-error" data-reveal>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <main class="page-stack">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
