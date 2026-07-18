/**
 * Displaying a stored amount in riel.
 *
 * Every amount in the database is USD (see App\Enums\Currency) — riel is a view
 * of it, converted at the rate on AppSetting. This is the display counterpart to
 * Currency::toUsd: that turns entered riel into stored dollars, this turns stored
 * dollars back into riel to show beside them.
 */
const RIEL = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

/**
 * Riel is quoted in whole units — there is no circulating subdivision, and the
 * smallest note is 100៛ — so cents would be noise on every figure.
 */
export function formatRiel(usd, khrPerUsd) {
    const amount = Number(usd);

    if (!Number.isFinite(amount)) {
        return '';
    }

    return `៛${RIEL.format(Math.round(amount * khrPerUsd))}`;
}
