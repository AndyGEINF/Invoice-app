<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Aplica el tema antes de pintar para evitar el parpadeo. Misma lógica que resources/js/lib/theme.ts. --}}
        <script>
            (function () {
                var preference = 'system';
                try { preference = localStorage.getItem('invoice.theme') || 'system'; } catch (e) {}
                var dark = preference === 'dark' || (preference !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        <x-inertia::head>
            <title>{{ config('app.name', 'INVOICE') }}</title>
        </x-inertia::head>
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        <x-inertia::app />
    </body>
</html>
