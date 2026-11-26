// SPDX-License-Identifier: Apache-2.0

// A reason is not an empty cell: "the question was asked, nobody knows yet".
const REASONS = {
    to_be_measured: { label: 'à mesurer', invite: true },
    firmware_dependent: { label: 'dépend du micrologiciel' },
    unknown: { label: 'inconnu : la question est posée' },
    not_published: { label: 'non publié par le fabricant' },
};

function formatNumber(field, value) {
    if (/_hz$/.test(field) && value >= 1000) {
        return `${(value / 1000).toLocaleString('fr-FR')} kHz`;
    }
    if (/_db$/.test(field)) {
        return `${value.toLocaleString('fr-FR')} dB`;
    }
    return value.toLocaleString('fr-FR');
}

export default function Value({ field, value }) {
    if (value === null || value === undefined) {
        return <span className="text-stone-400">absent</span>;
    }
    if (value === true) return 'oui';
    if (value === false) return 'non';
    if (typeof value === 'number') return formatNumber(field, value);
    if (typeof value === 'string' && REASONS[value]) {
        const reason = REASONS[value];
        return (
            <span className={reason.invite ? 'rounded bg-amber-100 px-1.5 py-0.5 text-amber-900' : 'italic text-stone-600'}>
                {reason.label}
                {reason.invite && ' : un porteur de l’appareil peut le remplir'}
            </span>
        );
    }
    if (typeof value === 'string') return value;
    // device_settings: [{setting, set_to, why}]
    if (Array.isArray(value) && value.every((v) => v && typeof v === 'object' && 'setting' in v)) {
        return (
            <ul className="list-disc pl-4">
                {value.map((v, i) => (
                    <li key={i}>
                        {v.setting} : <strong>{v.set_to}</strong>
                        {v.why && <span className="block text-xs text-stone-600">{v.why}</span>}
                    </li>
                ))}
            </ul>
        );
    }
    if (Array.isArray(value)) return value.map((v) => (typeof v === 'object' ? JSON.stringify(v) : String(v))).join(', ');
    return <code className="text-xs">{JSON.stringify(value)}</code>;
}
