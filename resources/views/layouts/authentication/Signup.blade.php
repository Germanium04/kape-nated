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
    <script src="{{ asset('js/Authenticate.js') }}" defer></script>
</head>
<body class="auth-page">
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
                        <input type="text" id="fname" name="fname"
                               class="field @error('fname') is-invalid @enderror"
                               value="{{ old('fname') }}" autocomplete="given-name" maxlength="100" required>
                        @error('fname') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" id="lname" name="lname"
                               class="field @error('lname') is-invalid @enderror"
                               value="{{ old('lname') }}" autocomplete="family-name" maxlength="100" required>
                        @error('lname') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username"
                               class="field @error('username') is-invalid @enderror"
                               value="{{ old('username') }}" autocomplete="username"
                               minlength="3" maxlength="50" required>
                        @error('username') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label for="contact">Contact</label>
                        <input type="tel" id="contact" name="contact"
                               class="field @error('contact') is-invalid @enderror"
                               value="{{ old('contact') }}" autocomplete="tel" maxlength="20" required>
                        @error('contact') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="branch">Branch</label>
                    <select id="branch" name="branch"
                            class="field @error('branch') is-invalid @enderror" required>
                        <option value="" disabled @selected(! old('branch'))>Select a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->name }}" @selected(old('branch') === $branch->name)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           class="field @error('password') is-invalid @enderror"
                           autocomplete="new-password" minlength="8" maxlength="255" required>
                    @error('password')
                        <span class="field-error">{{ $message }}</span>
                    @else
                        <span class="field-hint" style="font-size:13px;color:var(--line-soft)">At least 8 characters, with letters and numbers.</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn--primary" data-loading="Creating account…">Submit</button>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="{{ route('authentication.login') }}">Login</a>
            </p>
        </div>
    </div>

    @include('layouts.authentication.Ui-feedback')
</body>
</html>