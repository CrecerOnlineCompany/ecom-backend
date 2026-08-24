<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Ecom') }} Manage</title>
        <script>
            window.__MANAGE_USER__ = @json(auth()->user());
        </script>
        @vite(['resources/css/app.css', 'resources/js/manage.js'])
    </head>
    <body>
        <div id="manage-app"></div>
    </body>
</html>
