import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

/**
 * Surfaces the `flash` props shared by HandleInertiaRequests as toasts, so any
 * controller returning withSuccess()/withError() shows one without extra wiring.
 *
 * Call once, from the authenticated layout.
 */
export function useFlashToasts() {
    const page = usePage();

    watch(
        () => page.props.flash,
        (flash) => {
            if (!flash) {
                return;
            }

            if (flash.success) {
                toast.success(flash.success);
            }

            if (flash.error) {
                // Errors stay until dismissed — they usually need a decision.
                toast.error(flash.error, { duration: Infinity });
            }
        },
        // Flash arrives with the first page load too, not only on navigation.
        { immediate: true, deep: true },
    );
}
