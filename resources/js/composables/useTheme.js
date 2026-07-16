import { computed, ref } from 'vue';

const STORAGE_KEY = 'spendlog.theme';

/**
 * 'light' | 'dark' | 'system'. Kept module-level so every component that calls
 * useTheme() shares one source of truth rather than its own copy.
 */
const preference = ref(readStored());

const media = window.matchMedia('(prefers-color-scheme: dark)');

// Tracked separately from the preference: when the user is on 'system' and
// flips their OS theme, the page has to follow without a reload.
const systemPrefersDark = ref(media.matches);

media.addEventListener('change', (event) => {
    systemPrefersDark.value = event.matches;
    apply();
});

function readStored() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
        // Private mode / storage disabled — fall back rather than throw.
        return 'system';
    }
}

const isDark = computed(() =>
    preference.value === 'system'
        ? systemPrefersDark.value
        : preference.value === 'dark',
);

function apply() {
    document.documentElement.classList.toggle('dark', isDark.value);
    // Lets the browser theme form controls and scrollbars to match.
    document.documentElement.style.colorScheme = isDark.value ? 'dark' : 'light';
}

function setTheme(next) {
    preference.value = next;

    try {
        if (next === 'system') {
            localStorage.removeItem(STORAGE_KEY);
        } else {
            localStorage.setItem(STORAGE_KEY, next);
        }
    } catch {
        // Not being able to persist shouldn't stop the theme applying.
    }

    apply();
}

/** Straight light/dark flip — resolves 'system' to whatever it currently shows. */
function toggleTheme() {
    setTheme(isDark.value ? 'light' : 'dark');
}

export function useTheme() {
    return { preference, isDark, setTheme, toggleTheme };
}
