import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'spendlog.overBudgetDismissed';

/**
 * Whether the over-budget bar has been dismissed.
 *
 * Module-level, like useTheme's preference, and for a sharper reason here: the
 * layout that renders the bar is remounted on every Inertia visit, so a ref
 * declared inside its setup would reset to false and the bar would be back on
 * the next page. Dismissing it would hide it until you clicked anything.
 *
 * sessionStorage rather than localStorage: dismissing means "I know, not now",
 * not "never tell me again". Closing the tab should bring it back.
 */
const dismissed = ref(readStored());

function readStored() {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        // Private mode / storage disabled — show it rather than throw. It is a
        // warning, and it is still dismissible for as long as the page lives.
        return false;
    }
}

/**
 * The bar, and the state it needs. Call from the app shell.
 */
export function useOverBudget() {
    const page = usePage();

    // Null whenever there is nothing to say: no budget, or spend within it.
    // Shared by HandleInertiaRequests, so it refreshes on every visit — logging
    // an expense that tips the month over makes the bar appear by itself.
    const overBudget = computed(() => page.props.over_budget ?? null);

    /*
     * Not on the Budgets page. It already carries a fuller banner about the
     * month you are looking at, and two red bars saying the same thing teaches
     * you to read neither.
     */
    const onBudgetsPage = computed(() => route().current('budgets.*'));

    const showOverBudget = computed(
        () => overBudget.value !== null && ! dismissed.value && ! onBudgetsPage.value,
    );

    function dismissOverBudget() {
        dismissed.value = true;

        try {
            sessionStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // The ref above still hides it for this page's lifetime; only the
            // memory of it across a reload is lost.
        }
    }

    return { overBudget, showOverBudget, dismissOverBudget };
}
