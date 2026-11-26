// SPDX-License-Identifier: Apache-2.0
import { usePage } from '@inertiajs/react';
import en from './en.json';
import fr from './fr.json';

export const DICTIONARIES = { fr, en };
export const COOKIE = 'odac_lang';

const INTL = { fr: 'fr-FR', en: 'en-GB' };

function lookup(dictionary, key) {
    return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), dictionary);
}

function interpolate(text, vars) {
    return text.replace(/\{(\w+)\}/g, (match, name) => (vars[name] !== undefined ? String(vars[name]) : match));
}

/**
 * A translator for one language. No silent fallback to another language: a
 * missing key shows the key itself (and a test keeps FR and EN in step).
 */
export function translator(locale) {
    const dictionary = DICTIONARIES[locale] ?? DICTIONARIES.en;
    const intl = INTL[locale] ?? INTL.en;
    const plural = new Intl.PluralRules(intl);

    const has = (key) => typeof lookup(dictionary, key) === 'string';

    const t = (key, vars = {}) => {
        let value = lookup(dictionary, key);
        if (value && typeof value === 'object' && 'other' in value) {
            const n = Number(vars.n ?? 0);
            value = value[plural.select(n)] ?? value.other;
            vars = { ...vars, n: n.toLocaleString(intl) };
        }
        return typeof value === 'string' ? interpolate(value, vars) : key;
    };

    const number = (n, options) => Number(n).toLocaleString(intl, options);

    const date = (iso) => {
        if (!iso || !/^\d{4}-\d{2}-\d{2}/.test(iso)) return iso;
        const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
        return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString(intl, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
    };

    // A field code from the artifact is data: its label is translated, the
    // code itself never is.
    const field = (code) => {
        const parts = code.split('.');
        if (parts[0] === 'inputs' && parts.length === 3) {
            const iface = has(`interfaces.${parts[1]}`) ? t(`interfaces.${parts[1]}`) : parts[1];
            const inner = has(`fields.${parts[2]}`) ? t(`fields.${parts[2]}`) : parts[2];
            return `${iface} · ${inner}`;
        }
        // `usb.vid` -> `fields.usb_vid` (a dot would read as nesting).
        const key = `fields.${code.replace(/\./g, '_')}`;
        return has(key) ? t(key) : null;
    };

    return { t, has, number, date, field, locale };
}

export function useT() {
    const { locale } = usePage().props;
    return translator(locale);
}

/** Switches language: remembered in the preference cookie, then reload. */
export function switchLocale(locale) {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE}=${locale}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    const url = new URL(window.location.href);
    url.searchParams.delete('lang');
    window.location.assign(url.toString());
}
