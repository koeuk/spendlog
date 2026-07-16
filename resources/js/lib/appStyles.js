/**
 * The in-app counterpart to authStyles.js — same vocabulary as the login
 * screens (Plus Jakarta display face, neutral base, one green accent, 28px
 * corners) so the app does not read as a different product after sign-in.
 *
 * Spelled out in full: Tailwind scans source text, so a class assembled at
 * runtime would never be generated.
 */

/**
 * Page shell. Matches AUTH_PAGE, plus `relative` + `isolate` so the ambient
 * wash below sits behind the content without escaping the stacking context.
 */
export const APP_PAGE =
    'relative isolate min-h-screen bg-white font-display text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100';

/**
 * The hover lift, shared by every card so they all rise the same way.
 *
 * Thin on purpose: a tight 1px offset with a short blur reads as the card
 * peeling a millimetre off the page. A wide, soft shadow (shadow-md and up)
 * pushes it much further away than a hover should imply, and under a
 * translucent pane the spill is visible *through* the neighbouring card.
 *
 * The scale is deliberately tiny. These cards run up to ~1100px wide, so even
 * 1% would grow one by 11px and shove its text sideways; 0.4% is a breath.
 *
 * transform-gpu keeps the scale on the compositor — without it, scaling a
 * backdrop-blur surface re-rasterises the blur every frame.
 */
const CARD_LIFT =
    'transform-gpu transition-[box-shadow,transform] duration-200 ease-out ' +
    'hover:scale-[1.004] hover:shadow-[0_1px_3px_0_rgba(15,23,42,0.07),0_1px_2px_-1px_rgba(15,23,42,0.05)] ' +
    'dark:hover:shadow-[0_1px_3px_0_rgba(0,0,0,0.5),0_1px_2px_-1px_rgba(0,0,0,0.4)] ' +
    // Scale is motion; honour the OS setting and leave the shadow to do the job.
    'motion-reduce:transition-none motion-reduce:hover:scale-100';

/**
 * The glass surface.
 *
 * Frosted glass is only frosted if something shows through it — on a flat white
 * page a blurred card is indistinguishable from an opaque one. So this pairs
 * with the ambient wash rendered by the layout: translucent fill + backdrop
 * blur to bend it.
 *
 * The edge does the work at rest, not a shadow: a page of stacked cards each
 * casting its own drop shadow reads as clutter, where one hairline border per
 * card stays quiet. Depth is spent on hover instead, where it means something —
 * the card lifts to say it is the one under the pointer.
 *
 * Only `shadow` transitions. Animating the border or a transform would make a
 * long list shimmer as the pointer crosses it, and `transition-all` here would
 * also animate the backdrop filter, which is expensive on every card at once.
 */
export const CARD =
    `rounded-[28px] border border-neutral-200/80 bg-white/60 backdrop-blur-xl backdrop-saturate-150 ${CARD_LIFT} ` +
    'dark:border-white/10 dark:bg-neutral-900/50';

/**
 * The tinted glass — same green wash as the login artwork panel, made liquid.
 * Rests on its edge and lifts on hover exactly like CARD; a hero that sat under
 * a permanent drop shadow would break the rule the rest of the page follows.
 */
export const CARD_TINT =
    `rounded-[28px] border border-[#4b9d5f]/20 bg-[#eaf5e6]/70 backdrop-blur-xl backdrop-saturate-150 ${CARD_LIFT} ` +
    'dark:border-[#6cc182]/15 dark:bg-[#16281a]/60';

/**
 * A lighter pane for nested surfaces (modals, popovers) that sit above a card
 * and would otherwise blur an already-blurred layer — stacking backdrop filters
 * costs a lot and muddies both.
 */
export const CARD_SOLID =
    'rounded-[28px] border border-neutral-200/70 bg-white dark:border-neutral-800 dark:bg-neutral-900';

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
