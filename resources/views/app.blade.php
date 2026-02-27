<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <script>
            (() => {
                const key = 'agent-theme-preference';
                const root = document.documentElement;
                let preference = 'system';

                try {
                    const stored = window.localStorage.getItem(key);
                    if (stored === 'light' || stored === 'dark' || stored === 'system') {
                        preference = stored;
                    }
                } catch (error) {
                    // Ignore storage access errors (e.g. private browsing restrictions).
                }

                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolved = preference === 'dark' || (preference === 'system' && prefersDark) ? 'dark' : 'light';

                root.classList.toggle('dark', resolved === 'dark');
                root.style.colorScheme = resolved;
                root.dataset.themePreference = preference;
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
