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
    <link rel="stylesheet" href="{{ asset('style/Authenticate.css') }}">
    <link rel="stylesheet" href="{{ asset('style/Align.css') }}">
</head>
<body>

<header class="topbar">
    <a href="{{ route('admin.dashboard') }}" class="brand">
        kape&#8209;nated<span class="brand-bean">&#9679;</span>
    </a>

    <nav class="topnav">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-current' : '' }}">Dashboard</a>
        <a href="{{ route('admin.receipts') }}"  class="{{ request()->routeIs('admin.receipts')  ? 'is-current' : '' }}">Receipts</a>
        <a href="{{ route('admin.sales') }}"     class="{{ request()->routeIs('admin.sales')     ? 'is-current' : '' }}">Reports</a>
        <a href="{{ route('admin.menu') }}"      class="{{ request()->routeIs('admin.menu')      ? 'is-current' : '' }}">Menu</a>
        <a href="{{ route('admin.inventory') }}" class="{{ request()->routeIs('admin.inventory') ? 'is-current' : '' }}">Inventory</a>
    </nav>

    <div class="topbar-user" id="userMenu">
        <button type="button" class="user-trigger" id="userMenuBtn" aria-haspopup="true" aria-expanded="false">
            <span class="user-role">Admin</span>
            <span class="avatar" aria-hidden="true"></span>
        </button>

        {{--
                @if(Route::has('profile.edit'))
                    <a href="{{ route('profile.edit') }}" class="user-dropdown-item">Profile</a>
                @endif
        --}}
        <div class="user-dropdown" id="userDropdown" hidden>
            <div class="user-dropdown-head">
                <strong>Admin</strong>
            </div>

            <button type="button" class="user-dropdown-item" id="profilePlaceholder">Profile</button>
            <form method="POST" action="{{ route('authentication.logout') }}"
                  data-confirm="You'll need to log in again to get back in."
                  data-confirm-title="Log out?"
                  data-confirm-ok="Log out">
                @csrf
                <button type="submit" class="user-dropdown-item user-dropdown-item--danger">Log out</button>
            </form>
        </div>
    </div>
</header>

<main class="page">
    <div class="page-head">
        <div class="page-head-text">
            <h1>{{ $heading ?? $title }}</h1>
            @if($subheading)
                <p class="page-sub">{{ $subheading }}</p>
            @endif
        </div>

        {{-- Pages pass <x-slot:headerActions>; the old shell only looked for $actions,
             so the Inventory branch picker never rendered. Both names work now. --}}
        @isset($headerActions)
            <div class="page-actions">{{ $headerActions }}</div>
        @endisset
        @isset($actions)
            <div class="page-actions">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</main>


@include('layouts.authentication.Ui-feedback')
<script src="{{ asset('js/Authenticate.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
@stack('scripts')
</body>
</html>