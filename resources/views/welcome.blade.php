<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Ecom') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="landing">
            <h1>{{ config('app.name', 'Ecom') }}</h1>
            <nav>
                <a href="/t/demo">Frontend tienda demo</a>
                <a href="/admin">Admin custom</a>
            </nav>
        </main>
    </body>
</html>
