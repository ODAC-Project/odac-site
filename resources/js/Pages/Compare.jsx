// SPDX-License-Identifier: Apache-2.0
import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import FieldName from '../Components/FieldName';
import Icon from '../Components/Icon';
import Layout from '../Components/Layout';
import { Badge, Card, Empty, IdentityBadge, KindBadge, LevelBadge, PageHeader, SearchField } from '../Components/ui';
import Value from '../Components/Value';
import { useT } from '../i18n';

function retained(row) {
    if (!row) return null;
    if (row.contested) return row.contested.find((c) => c.chosen)?.value ?? row.value;
    return row.value;
}

export default function Compare({ sheets, options, max, family_names: familyNames }) {
    const { t } = useT();
    const [q, setQ] = useState('');
    const [onlyDiff, setOnlyDiff] = useState(false);
    const ids = sheets.map((s) => s.identity.odac_id);

    const go = (next) => router.get('/compare', next.length ? { d: next } : {}, { preserveScroll: true });

    const matches = useMemo(() => {
        const needle = q.trim().toLocaleLowerCase();
        if (needle.length < 2) return [];
        return options.filter((o) => !ids.includes(o.id) && o.label.toLocaleLowerCase().includes(needle)).slice(0, 8);
    }, [q, options, ids]);

    const rows = useMemo(() => {
        const order = [];
        const seen = new Set();
        sheets.forEach((s) =>
            s.summary.forEach((r) => {
                if (!seen.has(r.field)) {
                    seen.add(r.field);
                    order.push(r.field);
                }
            }),
        );
        const byDevice = sheets.map((s) => Object.fromEntries(s.summary.map((r) => [r.field, r])));
        // Fields every device carries come first: that is where a comparison says something.
        return order
            .map((field, index) => {
                const cells = byDevice.map((rows) => rows[field] ?? null);
                const values = cells.map((c) => JSON.stringify(retained(c) ?? null));
                const present = cells.filter((c) => c !== null && c.value !== null).length;
                return { field, cells, present, index, differs: sheets.length > 1 && new Set(values).size > 1 };
            })
            .sort((a, b) => b.present - a.present || a.index - b.index);
    }, [sheets]);

    const shown = onlyDiff ? rows.filter((r) => r.differs) : rows;
    const kindsDiffer = new Set(sheets.map((s) => s.identity.kind)).size > 1;
    const levelsDiffer = new Set(sheets.map((s) => s.identity.level)).size > 1;
    const identityDiffers = new Set(sheets.map((s) => s.identity.identified)).size > 1;
    const cell = (differs) => (differs ? 'bg-amber-400/[0.07]' : '');

    return (
        <Layout title={t('compare.title')} wide>
            <PageHeader title={t('compare.title')} intro={t('compare.intro', { max })} />

            <Card className="mb-5 p-4">
                {sheets.length < max ? (
                    <div className="relative max-w-xl">
                        <SearchField value={q} onChange={setQ} label={t('compare.add_label')} placeholder={t('compare.add_placeholder')} />
                        {q.trim().length >= 2 && (
                            <ul className="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-600 bg-ink-900 shadow-xl">
                                {matches.length === 0 && <li className="px-3 py-2 text-sm text-ink-400">{t('compare.not_found')}</li>}
                                {matches.map((o) => (
                                    <li key={o.id}>
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm text-ink-200 hover:bg-ink-800"
                                            onClick={() => {
                                                setQ('');
                                                go([...ids, o.id]);
                                            }}
                                        >
                                            {o.label}
                                            <Icon name="plus" className="h-4 w-4 shrink-0 text-accent-400" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-ink-400">{t('compare.max_reached')}</p>
                )}
            </Card>

            {sheets.length === 0 ? (
                <Empty>{t('compare.empty')}</Empty>
            ) : (
                <>
                    {sheets.length === 1 && <p className="mb-3 text-sm text-ink-400">{t('compare.need_more')}</p>}
                    {sheets.length > 1 && (
                        <label className="mb-3 inline-flex cursor-pointer items-center gap-2 text-sm text-ink-300">
                            <input type="checkbox" className="accent-[var(--color-accent-400)]" checked={onlyDiff} onChange={(e) => setOnlyDiff(e.target.checked)} />
                            {t('compare.only_diff')}
                        </label>
                    )}
                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm" style={{ minWidth: `${12 + sheets.length * 13}rem` }}>
                                <thead>
                                    <tr className="border-b border-ink-700/60 align-top">
                                        <th className="sticky left-0 w-48 bg-ink-850 px-4 py-4" />
                                        {sheets.map((s) => (
                                            <th key={s.identity.odac_id} className="px-4 py-4 font-normal">
                                                <div className="flex items-start justify-between gap-2">
                                                    <Link href={`/${s.identity.odac_id}`} className="group min-w-0">
                                                        <span className="block text-xs uppercase tracking-wide text-ink-400">{s.identity.brand}</span>
                                                        <span className="block font-semibold text-ink-100 group-hover:text-accent-300">{s.identity.model}</span>
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() => go(ids.filter((id) => id !== s.identity.odac_id))}
                                                        className="rounded p-1 text-ink-500 hover:bg-ink-800 hover:text-ink-200"
                                                        aria-label={t('compare.remove', { name: `${s.identity.brand} ${s.identity.model}` })}
                                                    >
                                                        <Icon name="close" className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-ink-700/50">
                                    {(!onlyDiff || kindsDiffer) && (
                                        <tr>
                                            <th scope="row" className="sticky left-0 bg-ink-850 px-4 py-3 font-normal text-ink-300">
                                                {t('compare.kind')}
                                            </th>
                                            {sheets.map((s) => (
                                                <td key={s.identity.odac_id} className={`px-4 py-3 ${cell(kindsDiffer)}`}>
                                                    <KindBadge kind={s.identity.kind} />
                                                </td>
                                            ))}
                                        </tr>
                                    )}
                                    {(!onlyDiff || identityDiffers) && (
                                        <tr>
                                            <th scope="row" className="sticky left-0 bg-ink-850 px-4 py-3 font-normal text-ink-300">
                                                {t('compare.identity')}
                                            </th>
                                            {sheets.map((s) => (
                                                <td key={s.identity.odac_id} className={`px-4 py-3 ${cell(identityDiffers)}`}>
                                                    <IdentityBadge identified={s.identity.identified} />
                                                </td>
                                            ))}
                                        </tr>
                                    )}
                                    {(!onlyDiff || levelsDiffer) && (
                                        <tr>
                                            <th scope="row" className="sticky left-0 bg-ink-850 px-4 py-3 font-normal text-ink-300">
                                                {t('compare.level')}
                                            </th>
                                            {sheets.map((s) => (
                                                <td key={s.identity.odac_id} className={`px-4 py-3 ${cell(levelsDiffer)}`}>
                                                    <LevelBadge level={s.identity.level} />
                                                </td>
                                            ))}
                                        </tr>
                                    )}
                                    {shown.map((r) => (
                                        <tr key={r.field} className="align-top">
                                            <th scope="row" className="sticky left-0 bg-ink-850 px-4 py-3 font-normal">
                                                <FieldName code={r.field} />
                                                {r.differs && (
                                                    <Badge tone="amber" className="mt-1">
                                                        {t('compare.differs')}
                                                    </Badge>
                                                )}
                                            </th>
                                            {r.cells.map((c, i) => (
                                                <td key={i} className={`px-4 py-3 text-ink-200 ${cell(r.differs)}`}>
                                                    {c === null ? (
                                                        <span className="text-ink-500">{t('values.absent')}</span>
                                                    ) : (
                                                        <>
                                                            <Value field={r.field} value={retained(c)} familyNames={familyNames} />
                                                            {c.contested && (
                                                                <Badge tone="rose" className="ml-1">
                                                                    {t('signals.contested')}
                                                                </Badge>
                                                            )}
                                                            {!c.proof && !c.contested && <span className="mt-0.5 block text-xs text-rose-300">{t('sheet.no_proof')}</span>}
                                                        </>
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </>
            )}
        </Layout>
    );
}
