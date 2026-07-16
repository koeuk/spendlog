/**
 * The rounded input used across every auth screen. Kept in one place because
 * five pages share it — Tailwind still sees the literal string here.
 */
export const PILL_INPUT =
    'h-[54px] rounded-full border-neutral-200 bg-white px-5 text-sm shadow-none transition placeholder:text-neutral-400 hover:border-neutral-300 focus-visible:border-neutral-900 focus-visible:ring-neutral-900/10';

/** The matching full-width submit button. */
export const PILL_BUTTON =
    'h-[54px] w-full rounded-full text-sm font-semibold active:translate-y-0 active:scale-[0.99]';
