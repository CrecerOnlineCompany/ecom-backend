<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ ucfirst(request()->route('store')) }} Store</title>
        <script>
            window.__STORE_KEY__ = @json(request()->route('store'));
            window.__AIMEOS_JSONAPI__ = @json(url('/jsonapi'));
        </script>
        @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    </head>
    <body>
        <div id="storefront-app"></div>
    </body>
</html>
