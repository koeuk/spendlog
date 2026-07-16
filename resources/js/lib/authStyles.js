/**
 * The rounded input used across every auth screen. Kept in one place because
 * five pages share it — Tailwind still sees the literal string here.
 *
 * The dark half is not optional: shadcn's Input already carries `dark:bg-input/30`,
 * so once `.dark` is on <html> the field darkens whether or not the page does.
 * Without a matching dark text colour it renders near-black text on a dark fill.
 */
export const PILL_INPUT =
    'h-[54px] rounded-full border-neutral-200 bg-white px-5 text-sm text-neutral-900 shadow-none transition placeholder:text-neutral-400 hover:border-neutral-300 focus-visible:border-neutral-900 focus-visible:ring-neutral-900/10 ' +
    'dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder:text-neutral-500 dark:hover:border-neutral-600 dark:focus-visible:border-neutral-300 dark:focus-visible:ring-neutral-100/10';

/** The matching full-width submit button. */
export const PILL_BUTTON =
    'h-[54px] w-full rounded-full text-sm font-semibold active:translate-y-0 active:scale-[0.99]';

/** Page shell for the auth screens. */
export const AUTH_PAGE = 'bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100';

/** Muted body copy under a heading. */
export const AUTH_MUTED = 'text-neutral-500 dark:text-neutral-400';

/** The green accent used for the cross-links (Register now / Log in). */
export const AUTH_LINK =
    'font-semibold text-[#4b9d5f] underline-offset-4 hover:underline dark:text-[#6cc182]';

/** Success / status banner. */
export const AUTH_BANNER =
    'rounded-2xl bg-[#eaf5e6] px-4 py-3 text-sm font-medium text-[#2f6b3d] dark:bg-[#16281a] dark:text-[#8fd4a0]';
