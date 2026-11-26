// SPDX-License-Identifier: Apache-2.0
import Layout from '../Components/Layout';
import { BarRow, Card, CardTitle, PageHeader } from '../Components/ui';
import { useT } from '../i18n';

function Panel({ title, children, className = '' }) {
    return (
        <Card className={`p-5 ${className}`}>
            <CardTitle className="mb-3">{title}</CardTitle>
            <div className="-mx-2 space-y-0.5">{children}</div>
        </Card>
    );
}

const maxOf = (rows, key) => Math.max(1, ...rows.map((r) => r[key]));

export default function Stats({ totals, by_kind: byKind, by_brand: byBrand, by_family: byFamily, families, by_level: byLevel, contributors, sources }) {
    const { t, number } = useT();
    const pct = (n) => t('stats.share', { p: totals.devices ? number(Math.round((n / totals.devices) * 100)) : 0 });

    return (
        <Layout title={t('stats.title')} wide>
            <PageHeader title={t('stats.title')} intro={t('stats.intro')} />

            <dl className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                {['brands', 'devices', 'claims', 'families'].map((k) => (
                    <div key={k} className="rounded-xl border border-ink-700/60 bg-ink-850 p-4">
                        <dt className="text-xs text-ink-400">{t(`home.${k}`)}</dt>
                        <dd className="mt-1 text-2xl font-semibold tabular-nums text-ink-100">{number(totals[k])}</dd>
                    </div>
                ))}
            </dl>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel title={t('stats.by_kind')}>
                    {byKind.map((r) => (
                        <BarRow key={r.kind} label={t(`kinds.${r.kind}`)} value={r.devices} max={totals.devices} hint={pct(r.devices)} href={`/devices?kind=${r.kind}`} />
                    ))}
                </Panel>
                <Panel title={t('stats.by_level')}>
                    {byLevel.map((r) => (
                        <BarRow
                            key={String(r.level)}
                            label={r.level === null ? t('levels.none') : `${t('levels.badge', { n: r.level })} · ${t(`levels.${r.level}`)}`}
                            value={r.devices}
                            max={totals.devices}
                            hint={pct(r.devices)}
                        />
                    ))}
                </Panel>
                <Panel title={t('stats.by_brand')}>
                    {byBrand.map((r) => (
                        <BarRow key={r.slug} label={r.name} value={r.devices} max={maxOf(byBrand, 'devices')} href={`/devices?brand=${encodeURIComponent(r.slug)}`} />
                    ))}
                </Panel>
                <Panel title={t('stats.by_family')}>
                    {byFamily.map((r) => (
                        <BarRow
                            key={r.id ?? 'none'}
                            label={r.id === null ? t('chips.not_set') : (families[r.id] ?? (r.id === 'unknown' ? t('values.reasons.unknown') : r.id))}
                            value={r.devices}
                            max={maxOf(byFamily, 'devices')}
                            href={r.id ? `/devices?family=${encodeURIComponent(r.id)}` : undefined}
                        />
                    ))}
                </Panel>
                <Panel title={t('stats.contributors')}>
                    {contributors.map((r) => (
                        <BarRow key={r.name} label={r.name === 'anonymous' ? t('common.anonymous') : r.name} value={r.claims} max={maxOf(contributors, 'claims')} />
                    ))}
                </Panel>
                <Panel title={t('stats.sources')}>
                    {sources.map((r) => (
                        <BarRow key={r.host} label={r.host} value={r.claims} max={maxOf(sources, 'claims')} />
                    ))}
                </Panel>
            </div>
        </Layout>
    );
}
