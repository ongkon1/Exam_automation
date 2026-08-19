<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login &middot; {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('asset/css/theme.css') }}" rel="stylesheet">
    <style>
        /* A soft version of the source site's hero glow behind the sign-in card. */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(60% 50% at 20% 15%, rgba(108, 99, 255, 0.12), transparent 70%),
                radial-gradient(50% 45% at 85% 85%, rgba(255, 60, 126, 0.1), transparent 70%);
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-mortarboard-fill fs-1" style="color: var(--pia-accent);"></i>
                <h1 class="h4 mt-2 gradient-text">{{ config('app.name') }}</h1>
                <p class="text-muted small mb-0">Sign in to continue</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @include('partials._errors')

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted small mt-3 mb-0">
                Accounts are created by teachers. Contact your teacher if you cannot sign in.
            </p>
        </div>
    </div>
</div>
</body>
</html>
