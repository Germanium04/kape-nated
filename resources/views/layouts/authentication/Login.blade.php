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
</head>
<body>
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
                    <input type="text" class="field" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="field" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn--primary">Submit</button>
            </form>
            <p class="auth-footer">
                Don't have an account yet? <a href="{{ route('authentication.signup') }}">Sign Up</a>
            </p>
        </div>
    </div>
</body>
</html>