// SPDX-License-Identifier: Apache-2.0
import { useT } from '../i18n';

// A reason is not an empty cell: "the question was asked, nobody knows yet".
const REASONS = ['to_be_measured', 'firmware_dependent', 'unknown', 'not_published', 'none'];
const INVITE = ['to_be_measured'];

function formatNumber(field, value, number) {
    if (/_hz$/.test(field) && value >= 1000) return `${number(value / 1000)} kHz`;
    if (/_db$/.test(field)) return `${number(value)} dB`;
    if (/(^|\.)bits$/.test(field)) return `${number(value)} bits`;
    return number(value);
}

export default function Value({ field, value, familyNames }) {
    const { t, number, locale } = useT();
    const colon = locale === 'fr' ? ' : ' : ': ';

    if (value === null || value === undefined) {
        return <span className="text-ink-500">{t('values.absent')}</span>;
    }
    if (value === true) return t('values.yes');
    if (value === false) return t('values.no');
    if (typeof value === 'number') return formatNumber(field, value, number);
    if (typeof value === 'string' && REASONS.includes(value)) {
        const invite = INVITE.includes(value);
        return (
            <span className={invite ? 'rounded bg-amber-400/10 px-1.5 py-0.5 text-amber-200 ring-1 ring-inset ring-amber-400/30' : 'italic text-ink-400'}>
                {t(`values.reasons.${value}`)}
                {invite && <span className="text-amber-200/80">
                        {colon}
                        {t('values.invite')}
                    </span>}
            </span>
        );
    }
    if (typeof value === 'string' && field === 'family' && familyNames?.[value]) {
        return (
            <span>
                {familyNames[value]} <code className="text-xs text-ink-500">{value}</code>
            </span>
        );
    }
    if (typeof value === 'string') return value;
    // device_settings: [{setting, set_to, why}]
    if (Array.isArray(value) && value.every((v) => v && typeof v === 'object' && 'setting' in v)) {
        return (
            <ul className="list-disc space-y-1 pl-4">
                {value.map((v, i) => (
                    <li key={i}>
                        {v.setting}
                        {colon}
                        <strong className="text-ink-100">{v.set_to}</strong>
                        {v.why && <span className="block text-xs text-ink-400">{v.why}</span>}
                    </li>
                ))}
            </ul>
        );
    }
    // Other settings shape: [{name, values, why_it_matters}]
    if (Array.isArray(value) && value.every((v) => v && typeof v === 'object' && 'name' in v)) {
        return (
            <ul className="list-disc space-y-1 pl-4">
                {value.map((v, i) => (
                    <li key={i}>
                        {v.name}
                        {Array.isArray(v.values) && (
                            <>
                                {colon}
                                <strong className="text-ink-100">{v.values.join(', ')}</strong>
                            </>
                        )}
                        {v.why_it_matters && <span className="block text-xs text-ink-400">{v.why_it_matters}</span>}
                    </li>
                ))}
            </ul>
        );
    }
    if (Array.isArray(value)) return value.map((v) => (typeof v === 'object' ? JSON.stringify(v) : String(v))).join(', ');
    return <code className="break-all text-xs text-ink-300">{JSON.stringify(value)}</code>;
}
