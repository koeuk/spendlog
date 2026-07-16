import { usePage } from '@inertiajs/vue3';

/**
 * Mirrors Laravel's __() against the lang/{locale}.json dictionary shared by
 * HandleInertiaRequests, so a key behaves the same in PHP and in Vue.
 *
 * An unknown key returns itself — the same fallback Laravel uses, which keeps
 * the UI readable when a translation is missing.
 *
 * @param {string} key
 * @param {Record<string, string|number>} replace  e.g. { name: 'Food' } for ':name'
 */
export function trans(key, replace = {}) {
    const translations = usePage().props.translations ?? {};
    let line = translations[key] ?? key;

    for (const [placeholder, value] of Object.entries(replace)) {
        line = line.replace(`:${placeholder}`, value);
    }

    return line;
}

/**
 * Resolves a translatable JSON field — {"en": "Food", "km": "អាហារ"} — to the
 * active locale, mirroring spatie/laravel-translatable's fallback to English.
 *
 * Tolerates a plain string so a non-translated field still renders.
 *
 * @param {Record<string, string>|string|null} field
 */
export function localized(field) {
    if (!field) {
        return '';
    }

    if (typeof field === 'string') {
        return field;
    }

    const locale = usePage().props.locale;

    return field[locale] || field.en || Object.values(field)[0] || '';
}

/**
 * Registers __() globally so templates can call it without importing.
 */
export const i18n = {
    install(app) {
        app.config.globalProperties.__ = trans;
        // Also expose for composition-API code that imports it directly.
        app.provide('__', trans);
    },
};
