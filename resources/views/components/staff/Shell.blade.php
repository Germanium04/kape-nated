@props([
    'title'      => 'Staff',
    'heading'    => null,
    'subheading' => null,
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · kape-nated</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('style/Admin.css') }}">
</head>
<body>

<header class="topbar">
    <a href="{{ route('staff.dashboard') }}" class="brand">
        kape&#8209;nated<span class="brand-bean">&#9679;</span>
    </a>

    <nav class="topnav">
        <a href="{{ route('staff.dashboard') }}" class="{{ request()->routeIs('staff.dashboard') ? 'is-current' : '' }}">Dashboard</a>
        <a href="{{ route('staff.receipts') }}"  class="{{ request()->routeIs('staff.orders')  ? 'is-current' : '' }}">Orders</a>
        <a href="{{ route('staff.inventory') }}" class="{{ request()->routeIs('staff.inventory') ? 'is-current' : '' }}">Inventory</a>
    </nav>

    <div class="topbar-user" id="userMenu">
        <button type="button" class="user-trigger" id="userMenuBtn" aria-haspopup="true" aria-expanded="false">
            <span class="user-role">Staff</span>
            <span class="avatar" aria-hidden="true"></span>
        </button>

        {{--

                @if(Route::has('profile.edit'))
                    <a href="{{ route('profile.edit') }}" class="user-dropdown-item">Profile</a>
                @endif
                @if(Route::has('logout'))
                    <form method="POST" action="{{ route('authentication.login') }}">
                        @csrf
                        <button type="submit" class="user-dropdown-item user-dropdown-item--danger">Log out</button>
                    </form>
                @endif
        --}}
        <div class="user-dropdown" id="userDropdown" hidden>
            <div class="user-dropdown-head">
                <strong>Staff</strong>
                <span class="muted">Prototype account — no login wired up yet</span>
            </div>

            <button type="button" class="user-dropdown-item" id="profilePlaceholder">Profile</button>
            <button type="button" class="user-dropdown-item user-dropdown-item--danger" id="logoutPlaceholder">Log out</button>
        </div>
    </div>
</header>

<main class="page">
    <div class="page-head">
        <h1>{{ $heading ?? $title }}</h1>
        @if($subheading)
            <p class="page-sub">{{ $subheading }}</p>
        @endif
        @isset($actions)
            <div class="page-actions">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</main>

<script src="{{ asset('js/admin.js') }}"></script>
@stack('scripts')
</body>
</html>