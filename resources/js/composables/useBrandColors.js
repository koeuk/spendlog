import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Keeps the admin's colours applied as they change.
 *
 * app.blade.php already applies them before first paint — that has to stay,
 * or every load would flash the default palette before Vue booted. But that
 * script runs once per *document*, and an Inertia visit swaps the page without
 * a document load. So saving a colour would persist it and change nothing on
 * screen until a hard refresh.
 *
 * This watches the shared props and re-applies. The rules below mirror the blade
 * script deliberately — see the note there before changing either.
 */
export function applyBrandColors(css, isDark) {
    if (!css) {
        return;
    }

    const root = document.documentElement;

    // Null means no brand colour is set. --primary is a theme-aware pair in
    // app.css (near-black on white, near-white on near-black), so pinning one
    // value over both would make the default button vanish into the dark page.
    if (css.primary) {
        root.style.setProperty('--primary', css.primary);
        root.style.setProperty('--primary-foreground', css.primaryForeground);
    } else {
        root.style.removeProperty('--primary');
        root.style.removeProperty('--primary-foreground');
    }

    // Always parked, even in dark mode: useTheme reads it back when the user
    // toggles to light, so it has to survive a spell in dark.
    root.style.setProperty('--brand-background', css.background);

    // The body colour is a light-mode choice only.
    if (isDark) {
        root.style.removeProperty('--background');
    } else {
        root.style.setProperty('--background', css.background);
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
        // Not immediate: blade has already applied the right values before this
        // ever runs, and re-applying on mount would only risk a wrong frame.
        { deep: true },
    );
}
