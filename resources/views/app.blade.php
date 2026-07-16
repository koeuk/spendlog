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

                    // Null while no brand colour is set: --primary is a
                    // theme-aware pair in app.css (near-black in light,
                    // near-white in dark), and pinning one value over both would
                    // make the default button vanish into the dark page.
                    if (t.primary) {
                        s.setProperty('--primary', t.primary);
                        s.setProperty('--primary-foreground', t.primaryForeground);
                    }

                    // The derived theme — every surface and text token, not just
                    // the page. Light mode only: dark mode has its own, built for
                    // a dark page. Stashed on the element either way so useTheme
                    // can re-apply it when the user toggles back to light, which
                    // it could not do from a value that was never written.
                    if (t.palette) {
                        window.__brandPalette = t.palette;

                        if (!dark) {
                            for (var token in t.palette) {
                                s.setProperty('--' + token, t.palette[token]);
                            }
                        }
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
