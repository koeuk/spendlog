import {
    Accessibility,
    Activity,
    Anchor,
    ArrowDown,
    ArrowUp,
    Bike,
    CircleDashed,
    Dumbbell,
    Flame,
    Footprints,
    HeartPulse,
    Mountain,
    MoveHorizontal,
    MoveVertical,
    Target,
    Timer,
    TrendingUp,
    Waves,
    Weight,
    Zap,
} from 'lucide-vue-next';

/**
 * The exercise module's icon registry.
 *
 * Separate from categoryStyles' ICONS because the two vocabularies are
 * different — that one is receipts and groceries, this one is movements. The
 * *colour* map is not duplicated: palette colours are domain-neutral, so
 * exerciseColor re-exports categoryColor rather than restating forty class
 * strings that would then have to be kept in sync.
 *
 * Keys mirror App\Enums\ExerciseIcon.
 */
const ICONS = {
    dumbbell: Dumbbell,
    weight: Weight,
    activity: Activity,
    'heart-pulse': HeartPulse,
    footprints: Footprints,
    bike: Bike,
    waves: Waves,
    mountain: Mountain,
    timer: Timer,
    flame: Flame,
    zap: Zap,
    'trending-up': TrendingUp,
    target: Target,
    anchor: Anchor,
    'arrow-up': ArrowUp,
    'arrow-down': ArrowDown,
    'move-horizontal': MoveHorizontal,
    'move-vertical': MoveVertical,
    'circle-dashed': CircleDashed,
    accessibility: Accessibility,
};

export const EXERCISE_ICON_NAMES = Object.keys(ICONS);

/** Returns null when there is no icon, which templates can v-if on. */
export function exerciseIcon(icon) {
    return ICONS[icon] ?? null;
}

// The palette is shared with the spending side — same ten Tailwind colours, same
// class strings. Re-exported rather than re-declared so a colour added there
// reaches both modules.
export { categoryColor as exerciseColor, CATEGORY_COLOR_NAMES as EXERCISE_COLOR_NAMES } from '@/lib/categoryStyles';

/**
 * Seconds as a human duration: 45s, 20m 30s, 1h 10m 8s.
 *
 * Every unit that has a value shows, largest first. Rounding to minutes hid
 * anything under thirty seconds behind "0m", which made a short session look
 * like it had not been timed at all.
 *
 * Empty units are dropped rather than padded — "1h 8s" is what happened, where
 * "1h 0m 8s" reads like a form field. A session of nothing still needs a
 * reading, so zero falls through to "0s".
 */
export function formatDuration(seconds) {
    if (seconds === null || seconds === undefined) {
        return '—';
    }

    const total = Math.max(0, Math.round(seconds));

    const parts = [
        [Math.floor(total / 3600), 'h'],
        [Math.floor((total % 3600) / 60), 'm'],
        [total % 60, 's'],
    ];

    return parts
        .filter(([value]) => value > 0)
        .map(([value, suffix]) => `${value}${suffix}`)
        .join(' ') || '0s';
}

/**
 * Seconds as a stopwatch reading: 05:32, 1:05:32.
 *
 * Distinct from formatDuration on purpose — a running clock needs fixed-width
 * digits that tick every second, where a logged session reads better rounded.
 */
export function formatClock(seconds) {
    const total = Math.max(0, Math.floor(seconds ?? 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;
    const pad = (n) => String(n).padStart(2, '0');

    return hours > 0 ? `${hours}:${pad(minutes)}:${pad(secs)}` : `${pad(minutes)}:${pad(secs)}`;
}

/** Metres as km when it is far enough to warrant it, else plain metres. */
export function formatDistance(metres) {
    if (metres === null || metres === undefined) {
        return '—';
    }

    return metres >= 1000 ? `${(metres / 1000).toFixed(2)} km` : `${metres} m`;
}

/** Kilograms in the viewer's unit, rounded to something a bar can hold. */
export function formatWeight(kilograms, unit = 'kg') {
    if (kilograms === null || kilograms === undefined) {
        return '—';
    }

    const value = unit === 'lb' ? kilograms / 0.45359237 : kilograms;

    // One decimal at most: plates come in half-kilo steps, and 62.499 kg is a
    // conversion artefact rather than a weight anyone loaded.
    return `${Math.round(value * 10) / 10} ${unit}`;
}
