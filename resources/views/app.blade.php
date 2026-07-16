<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Cached, so this does not cost a query per request. --}}
        @php($branding = \App\Models\AppSetting::current())

        <title inertia>{{ $branding->app_name }}</title>

        @if ($favicon = $branding->faviconUrl())
            <link rel="icon" href="{{ $favicon }}">
        @else
            {{-- No uploaded favicon: fall back to the built-in mark rather than
                 letting the browser 404 on /favicon.ico. --}}
            <link rel="icon" href="/favicon.ico">
        @endif

        {{--
            Runs before first paint, so a dark-mode user never sees a white
            flash while the Vite bundle loads. Mirrors resources/js/composables/useTheme.js.

            The admin's colours are applied here too, for the same reason — set
            from a mounted component they would land a frame late and the whole
            page would flick from the default palette to the branded one.

            Written as inline style on <html>, not a <style> block: an element's
            own style always beats a stylesheet rule, so this wins over both the
            :root tokens and the .dark overrides in app.css — and in dev, where
            Vite injects CSS via JS after this tag, a <style> block would lose.
        --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('spendlog.theme');
                    var dark = stored === 'dark' || (stored !== 'light'
                        && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

                    var t = @json($branding->cssVariables());
                    var s = document.documentElement.style;

                    // The brand button reads in either theme.
                    s.setProperty('--primary', t.primary);
                    s.setProperty('--primary-foreground', t.primaryForeground);

                    // Always parked here, even in dark mode, and never read by a
                    // token directly. It is the stash useTheme reads from when the
                    // user toggles back to light — without it, someone who loads
                    // the page in dark and switches to light would get the stock
                    // white instead of the admin's colour, with no way to recover
                    // it short of a reload.
                    s.setProperty('--brand-background', t.background);

                    // The body colour is a light-mode choice only: the presets are
                    // all near-white, and forcing one in dark mode would put pale
                    // text on a pale page.
                    if (!dark) {
                        s.setProperty('--background', t.background);
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
