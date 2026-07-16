import { onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * True while a GET visit is in flight, so a page can swap its list for
 * skeletons instead of leaving stale data on screen.
 *
 * GET only: a create/update/delete posts and then re-renders with the result,
 * and flashing skeletons behind an open dialog on every save reads as a glitch.
 */
export function useNavigating() {
    const navigating = ref(false);

    const stopStart = router.on('start', (event) => {
        navigating.value = event.detail.visit.method === 'get';
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
