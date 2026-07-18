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
    // bg-background, not bg-white: the shell covers the whole viewport, so a
    // literal white here paints straight over the admin's body colour and the
    // setting silently does nothing. The token resolves to the same white and
    // near-black by default, so this changes nothing until a colour is chosen.
    // text-foreground, not text-neutral-900: the page colour is derived, so the
    // text on it has to be too, or a dark background keeps near-black body copy.
    'relative isolate min-h-screen bg-background font-display text-foreground';

/**
 * The hover lift, shared by every card so they all rise the same way.
 *
 * Shadow only — the card does not move. A shadow alone still says "this is the
 * one under the pointer", and nothing shifts under the cursor while you read.
 *
 * Two layers, as real light casts: a wide soft one for the throw and a tight
 * dark one for the contact edge. A single big blur alone just looks like fog
 * under the card. Negative spread keeps the throw from bleeding sideways —
 * under a translucent pane, any spill shows *through* the neighbouring card.
 *
 * The curve is the same cubic-bezier the .anim entrance uses: it moves most of
 * the way early then settles, so the shadow fades up and away rather than
 * snapping off a linear ramp. 300ms in, and a slightly longer 400ms out — a
 * lift that leaves as fast as it arrives feels snatched away.
 */
const CARD_LIFT =
    'transition-shadow duration-[400ms] ease-[cubic-bezier(0.22,1,0.36,1)] hover:duration-300 ' +
    'hover:shadow-[0_8px_18px_-8px_rgba(15,23,42,0.14),0_2px_6px_-3px_rgba(15,23,42,0.08)] ' +
    'dark:hover:shadow-[0_8px_18px_-8px_rgba(0,0,0,0.6),0_2px_6px_-3px_rgba(0,0,0,0.45)]';

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
    // bg-card/border-border, not literal white: a translucent *white* card over a
    // tinted page is just a washed-out patch of that tint. The tokens are derived
    // from the background, so the card sits a measured step off it in the same
    // hue — and still resolve to today's white and near-black by default.
    `rounded-[28px] border border-border bg-card/70 backdrop-blur-xl backdrop-saturate-150 ${CARD_LIFT}`;

/**
 * The tinted glass — same green wash as the login artwork panel, made liquid.
 * Rests on its edge and lifts on hover exactly like CARD; a hero that sat under
 * a permanent drop shadow would break the rule the rest of the page follows.
 */
export const CARD_TINT =
    `rounded-[28px] border border-[#4b9d5f]/20 bg-[#eaf5e6]/70 backdrop-blur-xl backdrop-saturate-150 ${CARD_LIFT} ` +
    'dark:border-[#6cc182]/15 dark:bg-[#16281a]/60';

/**
 * CARD_TINT's alarm state — same glass, red — for a message the page needs read
 * before the numbers under it.
 *
 * No CARD_LIFT: everything else that lifts is a thing you click, and lifting
 * under the pointer would promise an action this does not have. Red is spelled
 * out for both themes rather than taken from --destructive, which is a *fill*
 * for buttons: at 70% opacity behind text it is a solid red slab, not a wash.
 */
export const CARD_ALERT =
    'rounded-[28px] border border-red-500/20 bg-red-50/70 backdrop-blur-xl backdrop-saturate-150 ' +
    'dark:border-red-500/25 dark:bg-red-950/40';

/**
 * CARD's branded sibling — the same glass, washed with the admin's button
 * colour, for a block that should read as the app speaking rather than as
 * another data card.
 *
 * --primary, not a literal: this is the one surface meant to carry the brand,
 * so it has to move when an admin picks a colour instead of pinning a hue the
 * rest of the app no longer uses.
 *
 * 10% fill and a 20% edge, because --primary is a *button* fill: at full
 * strength it is a solid slab behind body copy. A tenth of it reads as a tint
 * on both themes while leaving the derived card text its contrast.
 *
 * No dark: variant, and no CARD_LIFT — matching ACTIVE and CARD_ALERT
 * respectively. --primary is already theme-aware, and nothing here is clickable.
 */
export const CARD_BRAND =
    'rounded-[28px] border border-primary/20 bg-primary/10 ' +
    'backdrop-blur-xl backdrop-saturate-150';

/**
 * A lighter pane for nested surfaces (modals, popovers) that sit above a card
 * and would otherwise blur an already-blurred layer — stacking backdrop filters
 * costs a lot and muddies both.
 */
export const CARD_SOLID =
    'rounded-[28px] border border-border bg-popover';

/**
 * The fill for the selected one of a set — the active nav tab, the chosen
 * segment, the current settings page.
 *
 * The token, not a literal: it tracks the admin's button colour, so choosing a
 * brand colour moves every one of these at once instead of leaving black pills
 * scattered around a red app.
 *
 * No dark: variant, deliberately. --primary is already theme-aware on its own —
 * near-black on white, near-white on near-black — and once a brand colour is set
 * it is a single value that belongs in both. A dark: override here would fight
 * both cases.
 */
export const ACTIVE = 'bg-primary text-primary-foreground';

/** Muted body copy. Derived to clear AA on the card it sits on. */
export const MUTED = 'text-muted-foreground';

/** The green accent, matching AUTH_LINK. */
export const ACCENT = 'text-[#4b9d5f] dark:text-[#6cc182]';

/** Section label above a card's content. */
export const EYEBROW =
    'text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground';

/** The big number a card exists to show. */
export const FIGURE =
    'font-extrabold tracking-[-0.03em] tabular-nums text-foreground';

/** Compact pill button, e.g. "Add expense" in a page header. */
export const PILL_ACTION =
    'h-10 rounded-full px-4 text-sm font-semibold active:translate-y-0 active:scale-[0.99]';

/**
 * A quiet outline pill for a secondary action beside a heading — the PDF and
 * Excel downloads on Reports.
 *
 * Sizeless: the page header uses a taller pill than the one tucked into the
 * expenses card, so the caller adds its own height and padding.
 *
 * Hover fills with ACTIVE — the same --primary the selected nav tab and the
 * chosen period segment wear — and the fill rises from the bottom edge rather
 * than cross-fading in place, so the pill reads as filling up. A pill this small
 * cannot rely on an opacity step (70% to solid is a change you have to already
 * be looking for), and reusing the selected-state fill means the page has one
 * colour for "this one", whether it is chosen or merely under the pointer.
 *
 * Spelled out rather than interpolating ACTIVE: Tailwind scans source text, so
 * `hover:${ACTIVE}` would compile to classes that were never generated. The
 * token still tracks the admin's button colour, so this follows a brand colour
 * instead of pinning green.
 *
 * Eased on the same curve as CARD_LIFT, and asymmetric for the same reason: in
 * over 200ms, out over 300, so it settles rather than snapping off Tailwind's
 * bare `transition` (a 150ms linear ramp).
 *
 * transition-colors, not transition-all: only fill, border and text move, and
 * animating everything would drag the backdrop blur behind it on every pointer
 * pass.
 */
export const EXPORT_LINK =
    'inline-flex items-center gap-1.5 rounded-full border border-border bg-card/70 ' +
    'text-xs font-semibold text-foreground ' +
    // The fill is a background *image* — a flat primary-to-primary gradient — so
    // bg-card/70 stays underneath as the background *colour* and the two do not
    // fight. Pinned to the bottom edge at zero height, it grows upward on hover.
    'bg-[linear-gradient(to_top,var(--color-primary),var(--color-primary))] ' +
    'bg-[length:100%_0%] bg-bottom bg-no-repeat ' +
    'transition-[background-size,color,border-color] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] ' +
    'hover:border-primary hover:bg-[length:100%_100%] hover:text-primary-foreground hover:duration-200';

/** A segmented control (Mine/Everyone, EN/KM). */
export const SEGMENT =
    'inline-flex rounded-full border border-border bg-muted p-0.5';

export const SEGMENT_ON = `rounded-full ${ACTIVE}`;
export const SEGMENT_OFF =
    'rounded-full text-muted-foreground hover:text-foreground';
