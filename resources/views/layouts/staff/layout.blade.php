<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Staff') · kape-nated</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@800&family=Work+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="{{ route('staff.order') }}">kape-nated &#9749;</a>
    <nav class="nav">
      <a href="{{ route('staff.order') }}" class="{{ request()->routeIs('staff.order') ? 'active' : '' }}">Order</a>
      <a href="{{ route('staff.inventory') }}" class="{{ request()->routeIs('staff.inventory') ? 'active' : '' }}">Inventory</a>
      {{-- Avatar doubles as sign-out so there is no extra button to design. --}}
      <form method="POST" action="{{ route('logout') }}">@csrf
        <button class="avatar" title="Sign out {{ auth()->user()->name }}" aria-label="Sign out"></button>
      </form>
    </nav>
  </header>
  @yield('content')
</body>
</html>
