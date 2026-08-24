<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ ucfirst(request()->route('store')) }} Store</title>
        @php
            $storefrontContacts = [
                'whatsapp' => env('STOREFRONT_WHATSAPP'),
                'whatsappMessage' => env('STOREFRONT_WHATSAPP_MESSAGE', 'Hola, quiero consultar por un producto.'),
                'instagram' => env('STOREFRONT_INSTAGRAM_URL'),
                'facebook' => env('STOREFRONT_FACEBOOK_URL'),
                'tiktok' => env('STOREFRONT_TIKTOK_URL'),
                'youtube' => env('STOREFRONT_YOUTUBE_URL'),
            ];
        @endphp
        <script>
            window.__STORE_KEY__ = @json(request()->route('store'));
            window.__AIMEOS_JSONAPI__ = @json(url('/jsonapi'));
            window.__STOREFRONT_CONTACTS__ = @json($storefrontContacts);
        </script>
        @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    </head>
    <body>
        <div id="storefront-app"></div>
    </body>
</html>
