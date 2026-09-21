@props([
    'title'      => 'Admin',
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
    <a href="{{ route('admin.dashboard') }}" class="brand">
        kape&#8209;nated<span class="brand-bean">&#9679;</span>
    </a>

    <nav class="topnav">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-current' : '' }}">Dashboard</a>
        <a href="{{ route('admin.receipts') }}"  class="{{ request()->routeIs('admin.receipts')  ? 'is-current' : '' }}">Receipts</a>
        <a href="{{ route('admin.sales') }}"     class="{{ request()->routeIs('admin.sales')     ? 'is-current' : '' }}">Sales</a>
        <a href="{{ route('admin.menu') }}"      class="{{ request()->routeIs('admin.menu')      ? 'is-current' : '' }}">Menu</a>
        <a href="{{ route('admin.inventory') }}" class="{{ request()->routeIs('admin.inventory') ? 'is-current' : '' }}">Inventory</a>
    </nav>

    <div class="topbar-user" id="userMenu">
        <button type="button" class="user-trigger" id="userMenuBtn" aria-haspopup="true" aria-expanded="false">
            <span class="user-role">Admin</span>
            <span class="avatar" aria-hidden="true"></span>
        </button>

        {{--
            This admin section has no real login wired up yet, so this dropdown
            deliberately doesn't touch Breeze's actual /logout or /profile routes —
            those require an authenticated session, and hitting them without one
            just bounces you to the login page, which is what was happening before.

            Once staff/admin accounts and a real login are built, swap the two
            buttons below for:

                @if(Route::has('profile.edit'))
                    <a href="{{ route('profile.edit') }}" class="user-dropdown-item">Profile</a>
                @endif
                @if(Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="user-dropdown-item user-dropdown-item--danger">Log out</button>
                    </form>
                @endif
        --}}
        <div class="user-dropdown" id="userDropdown" hidden>
            <div class="user-dropdown-head">
                <strong>Admin</strong>
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