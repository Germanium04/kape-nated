<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('style/Authenticate.css') }}">
</head>
<body>
    <div class="auth-signup">
        <div class="auth-form">
            <div class="auth-title">
                <h2>Welcome to Kape-nated!</h2>
                <p>Sign up to get started</p>
            </div>

            <form method="POST" action="{{ route('authentication.signup.submit') }}">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" class="field" id="fname" name="fname" required>
                    </div>

                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" class="field" id="lname" name="lname" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="field" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact</label>
                        <input type="tel" class="field" id="contact" name="contact" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="branch">Branch</label>
                    <input type="text" class="field" id="branch" name="branch" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="field" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn--primary">Submit</button>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="{{ route('authentication.login') }}">Login</a>
            </p>
        </div>
    </div>
</body>
</html>