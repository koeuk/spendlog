/**
 * The in-app counterpart to authStyles.js — same vocabulary as the login
 * screens (Plus Jakarta display face, neutral base, one green accent, 28px
 * corners) so the app does not read as a different product after sign-in.
 *
 * Spelled out in full: Tailwind scans source text, so a class assembled at
 * runtime would never be generated.
 */

/** Page shell. Matches AUTH_PAGE. */
export const APP_PAGE =
    'min-h-screen bg-white font-display text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100';

/** The standard surface: a soft card on the page. */
export const CARD =
    'rounded-[28px] border border-neutral-200/70 bg-white dark:border-neutral-800 dark:bg-neutral-900';

/** The tinted surface — same green wash as the login artwork panel. */
export const CARD_TINT = 'rounded-[28px] bg-[#f1f7ef] dark:bg-[#0f1a12]';

/** Muted body copy. Matches AUTH_MUTED. */
export const MUTED = 'text-neutral-500 dark:text-neutral-400';

/** The green accent, matching AUTH_LINK. */
export const ACCENT = 'text-[#4b9d5f] dark:text-[#6cc182]';

/** Section label above a card's content. */
export const EYEBROW =
    'text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-400 dark:text-neutral-500';

/** The big number a card exists to show. */
export const FIGURE =
    'font-extrabold tracking-[-0.03em] tabular-nums text-neutral-900 dark:text-neutral-100';

/** Compact pill button, e.g. "Add expense" in a page header. */
export const PILL_ACTION =
    'h-10 rounded-full px-4 text-sm font-semibold active:translate-y-0 active:scale-[0.99]';

/** A segmented control (Mine/Everyone, EN/KM). */
export const SEGMENT =
    'inline-flex rounded-full border border-neutral-200 bg-white p-0.5 dark:border-neutral-700 dark:bg-neutral-800';

export const SEGMENT_ON = 'rounded-full bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900';
export const SEGMENT_OFF =
    'rounded-full text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-700/60';
