import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * The session stopwatch and the between-sets rest countdown.
 *
 * Client-side only, by design: the timer's reading is written into the workout
 * form when you finish, and the workout is what gets persisted. Closing the tab
 * mid-session loses the clock — the trade for not modelling an "active session"
 * server-side. If that becomes a problem, the fix is a started_at column on
 * workouts, not a bigger composable.
 *
 * Elapsed time is derived from wall-clock timestamps rather than counted up on
 * each tick. A setInterval is throttled to once a minute in a background tab, so
 * an incrementing counter would lose most of a set the moment you switched away
 * to a music app — the exact thing a gym timer must survive.
 */
export function useSessionTimer() {
    const running = ref(false);
    // Milliseconds banked by previous run/pause cycles.
    const accumulated = ref(0);
    const startedAt = ref(null);
    // Bumped by the interval purely to invalidate the computed below; the value
    // itself is never read.
    const tick = ref(0);

    let handle = null;

    const elapsedSeconds = computed(() => {
        // Touch the tick so this recomputes each second while running.
        void tick.value;

        const live = running.value && startedAt.value !== null ? Date.now() - startedAt.value : 0;

        return Math.floor((accumulated.value + live) / 1000);
    });

    function start() {
        if (running.value) {
            return;
        }

        running.value = true;
        startedAt.value = Date.now();
        handle = setInterval(() => (tick.value += 1), 250);
    }

    function pause() {
        if (!running.value) {
            return;
        }

        accumulated.value += Date.now() - startedAt.value;
        running.value = false;
        startedAt.value = null;
        clearInterval(handle);
        handle = null;
    }

    function reset() {
        pause();
        accumulated.value = 0;
        tick.value = 0;
    }

    function toggle() {
        running.value ? pause() : start();
    }

    // A timer left running when the dialog closes would keep an interval alive
    // for the life of the page.
    onBeforeUnmount(() => clearInterval(handle));

    return { running, elapsedSeconds, start, pause, reset, toggle };
}

/**
 * A countdown for rest between sets.
 *
 * Separate from the stopwatch above because it answers a different question —
 * "how long until I go again" rather than "how long have I been here" — and the
 * two run at once during a session.
 */
export function useRestTimer(defaultSeconds = 90) {
    const duration = ref(defaultSeconds);
    const endsAt = ref(null);
    const tick = ref(0);

    let handle = null;

    const remaining = computed(() => {
        void tick.value;

        if (endsAt.value === null) {
            return duration.value;
        }

        return Math.max(0, Math.ceil((endsAt.value - Date.now()) / 1000));
    });

    const running = computed(() => endsAt.value !== null && remaining.value > 0);
    const finished = computed(() => endsAt.value !== null && remaining.value === 0);

    function start(seconds = null) {
        if (seconds !== null) {
            duration.value = seconds;
        }

        endsAt.value = Date.now() + duration.value * 1000;
        clearInterval(handle);
        handle = setInterval(() => (tick.value += 1), 250);
    }

    function stop() {
        endsAt.value = null;
        clearInterval(handle);
        handle = null;
    }

    onBeforeUnmount(() => clearInterval(handle));

    return { duration, remaining, running, finished, start, stop };
}
