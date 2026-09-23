<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('style/Authenticate.css') }}">
</head>
<body>
    <div class="auth-login">
        <div class="auth-form">
            <div class="auth-title">
                <h2>Welcome to Kape-nated!</h2>
                <p>Login to your account</p>
            </div>
            <form method="POST" action="{{ route('admin.dashboard') }}">
                @csrf
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" class="field" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
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