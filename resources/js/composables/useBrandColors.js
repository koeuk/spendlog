import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Every token the generated palette can set. Kept explicit so removing a theme
 * clears exactly what it added — iterating the *new* palette would strip nothing
 * when there is no new palette, which is the case that matters.
 *
 * --border and --input are deliberately absent: the palette does not derive them
 * (see Palette::from), so app.css owns them and nothing here may clear them.
 * This list must stay in step with that one.
 */
const PALETTE_TOKENS = [
    'background',
    'foreground',
    'card',
    'card-foreground',
    'popover',
    'popover-foreground',
    'secondary',
    'secondary-foreground',
    'accent',
    'accent-foreground',
    'muted',
    'muted-foreground',
    'ring',
];

/**
 * Applies the admin's theme to the document.
 *
 * app.blade.php already does this before first paint — that has to stay, or every
 * load would flash the stock palette before Vue booted. But that script runs once
 * per *document*, and an Inertia visit swaps the page without one. So a saved
 * colour would persist and change nothing on screen until a hard refresh.
 *
 * The rules here mirror the blade script deliberately. Change one, change both.
 */
export function applyBrandColors(css, isDark) {
    if (!css) {
        return;
    }

    const root = document.documentElement;

    /*
     * Null means no brand colour is set. --primary is a theme-aware pair in
     * app.css (near-black on white, near-white on near-black), so pinning one
     * value across both would make the default button vanish into the dark page.
     */
    if (css.primary) {
        root.style.setProperty('--primary', css.primary);
        root.style.setProperty('--primary-foreground', css.primaryForeground);
    } else {
        root.style.removeProperty('--primary');
        root.style.removeProperty('--primary-foreground');
    }

    // Stashed even while dark mode is on, so toggling back to light can restore
    // it without a round trip.
    if (css.palette) {
        window.__brandPalette = css.palette;
    } else {
        delete window.__brandPalette;
    }

    applyPalette(isDark ? null : css.palette);

    // The silver preset's opaque cards — see app.css. Light mode only, and
    // stashed so a dark→light toggle can put it back, the same way the palette
    // is. Mirrors the pre-paint script in app.blade.php; change one, change both.
    window.__solidCards = !!css.solidCards;
    root.classList.toggle('solid-cards', window.__solidCards && !isDark);
}

/**
 * Writes a palette to the root, or clears every token it would have set.
 *
 * Passing null is the load-bearing case: dark mode, or a theme being removed.
 * Clearing lets app.css's own tokens take back over rather than leaving a light
 * theme's card colour stranded on a dark page.
 */
export function applyPalette(palette) {
    const root = document.documentElement;

    for (const token of PALETTE_TOKENS) {
        if (palette?.[token]) {
            root.style.setProperty(`--${token}`, palette[token]);
        } else {
            root.style.removeProperty(`--${token}`);
        }
    }
}

/**
 * Call once, from the app shell.
 */
export function useBrandColors(isDark) {
    const page = usePage();

    watch(
        [() => page.props.branding?.css, isDark],
        ([css, dark]) => applyBrandColors(css, dark),
        /*
         * Immediate, and it has to be. The shell this runs from is remounted on
         * every Inertia visit, so the watcher is destroyed and rebuilt with a
         * fresh baseline each navigation — it can never fire for a change made
         * between page loads, which is the only thing it exists to catch.
         * Applying on mount is what makes a saved colour reach the DOM at all.
         *
         * Safe on a full load, where blade has already written these values:
         * the rules here mirror it exactly, so this re-applies what is on the
         * element already. Idempotent, and no frame in between.
         */
        { deep: true, immediate: true },
    );
}
