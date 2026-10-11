<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('style/Authenticate.css') }}">
    <script src="{{ asset('js/Authenticate.js') }}" defer></script>
</head>
<body class="auth-page">
    <div class="auth-login">
        <div class="auth-form">
            <div class="auth-title">
                <h2>Welcome to Kape-nated!</h2>
                <p>Login to your account</p>
            </div>

            <form method="POST" action="{{ route('authentication.login.submit') }}">
                @csrf

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           class="field @error('username') is-invalid @enderror"
                           value="{{ old('username') }}"
                           autocomplete="username" maxlength="255" required autofocus>
                    @error('username')
                        <span class="field-error" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           class="field @error('password') is-invalid @enderror"
                           autocomplete="current-password" maxlength="255" required>
                    @error('password')
                        <span class="field-error" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn--primary" data-loading="Logging in…">Submit</button>
            </form>

            <p class="auth-footer">
                Don't have an account yet? <a href="{{ route('authentication.signup') }}">Sign Up</a>
            </p>
        </div>
    </div>

    @include('layouts.authentication.Ui-feedback')
</body>
</html>