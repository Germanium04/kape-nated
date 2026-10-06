@props([
    'title' => 'Staff',
])

@php
    $me     = auth()->user();
    $first  = \Illuminate\Support\Str::before($me->name, ' ');
    $myBranch = $me->branch_id
        ? \Illuminate\Support\Facades\DB::table('branches')->where('id', $me->branch_id)->value('name')
        : null;
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · kape-nated</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('style/Staff.css') }}">
</head>
<body>

<header class="topbar">
    <a class="brand" href="{{ route('staff.dashboard') }}">kape&#8209;nated<span class="brand-bean">&#9679;</span></a>

    <nav class="topnav">
        <a href="{{ route('staff.dashboard') }}" class="{{ request()->routeIs('staff.dashboard') ? 'is-current' : '' }}">Dashboard</a>
        <a href="{{ route('staff.orders') }}"    class="{{ request()->routeIs('staff.orders')    ? 'is-current' : '' }}">Order</a>
        <a href="{{ route('staff.inventory') }}" class="{{ request()->routeIs('staff.inventory') ? 'is-current' : '' }}">Inventory</a>
    </nav>

    <div class="topbar-user" id="userMenu">
        <button type="button" class="user-trigger" id="userMenuBtn" aria-haspopup="true" aria-expanded="false">
            <span class="user-role">{{ $first }}</span>
            <span class="avatar" aria-hidden="true"></span>
        </button>

        <div class="user-dropdown" id="userDropdown" hidden>
            <div class="user-dropdown-head">
                <strong>{{ $me->name }}</strong>
                <span class="muted">{{ $myBranch ?? 'No branch assigned' }}</span>
            </div>

            <form method="POST" action="{{ route('authentication.logout') }}">
                @csrf
                <button type="submit" class="user-dropdown-item user-dropdown-item--danger">Log out</button>
            </form>
        </div>
    </div>
</header>

{{ $slot }}

<script>
    // User menu: opens on click, closes on outside click or Esc.
    (() => {
        const btn  = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userDropdown');
        const close = () => { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); };

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.hidden = !menu.hidden;
            btn.setAttribute('aria-expanded', String(!menu.hidden));
        });
        document.addEventListener('click', (e) => { if (!menu.hidden && !menu.contains(e.target)) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    })();
</script>
<script src="{{ asset('js/staff.js') }}"></script>
@stack('scripts')
</body>
</html>