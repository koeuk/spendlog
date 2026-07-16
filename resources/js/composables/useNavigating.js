import { onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * True while an Inertia visit is in flight, so a page can swap its list for
 * skeletons instead of leaving stale data on screen.
 *
 * Ignores background visits (partial reloads with no visual result).
 */
export function useNavigating() {
    const navigating = ref(false);

    const stopStart = router.on('start', () => {
        navigating.value = true;
    });

    const stopFinish = router.on('finish', () => {
        navigating.value = false;
    });

    onUnmounted(() => {
        stopStart();
        stopFinish();
    });

    return { navigating };
}
