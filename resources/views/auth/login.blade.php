<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login | {{ config('app.name', 'Ecom') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-page">
            <form class="auth-card" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div>
                    <h1>Ingresar</h1>
                    <p>Acceso administrativo</p>
                </div>

                <label>
                    <span>Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </label>

                <label>
                    <span>Password</span>
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>

                <label class="check-row">
                    <input name="remember" type="checkbox" value="1">
                    <span>Recordarme</span>
                </label>

                @if ($errors->any())
                    <p class="auth-error">{{ $errors->first() }}</p>
                @endif

                <button type="submit">Ingresar</button>
            </form>
        </main>
    </body>
</html>
