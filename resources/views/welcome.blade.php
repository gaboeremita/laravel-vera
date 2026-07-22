<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="/favicon.png">

        <!-- Self-hosted VAD bundle (onnxruntime-web is CJS/require-based and breaks under Vite's ESM dep pre-bundling) -->
        <script src="/vendor/vad/bundle.min.js"></script>

        @fonts

        <!-- Styles / Scripts -->
        @viteReactRefresh
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
