import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Whether the over-budget bar has been dismissed this page load.
 *
 * Module-level, not declared inside the composable: the layout that renders the
 * bar is remounted on every Inertia visit, so a ref inside setup would reset to
 * false and the bar would reappear on the next page — dismissing it would hide
 * it only until you clicked anything.
 *
 * Kept in memory and nowhere else, on purpose. A full page refresh reloads this
 * module, which resets the flag, so refreshing while still over budget brings
 * the bar back. Dismissing means "hide it right now", and a reload is a fresh
 * look. It still survives Inertia navigation, so clicking around after
 * dismissing does not re-nag within the same page load.
 */
const dismissed = ref(false);

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
    }

    return { overBudget, showOverBudget, dismissOverBudget };
}
