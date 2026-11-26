// SPDX-License-Identifier: Apache-2.0
import { Link } from '@inertiajs/react';
import FieldName from '../Components/FieldName';
import Layout from '../Components/Layout';
import { Badge, Card, CardTitle, PageHeader } from '../Components/ui';
import { useT } from '../i18n';

function Ring({ value, total }) {
    const share = total ? value / total : 0;
    const r = 30;
    const c = 2 * Math.PI * r;
    return (
        <svg viewBox="0 0 72 72" className="h-20 w-20 shrink-0 -rotate-90" aria-hidden="true">
            <circle cx="36" cy="36" r={r} fill="none" stroke="var(--color-ink-750)" strokeWidth="8" />
            {share > 0 && <circle cx="36" cy="36" r={r} fill="none" stroke="var(--color-accent-400)" strokeWidth="8" strokeLinecap="round" strokeDasharray={`${share * c} ${c}`} />}
        </svg>
    );
}

export default function Health({ totals, levels, health }) {
    const { t, number } = useT();
    const worst = Math.max(1, ...health.missing.map((m) => m.missing));

    const signals = [
        { key: 'contested', value: health.contested, href: '/devices?contested=1' },
        { key: 'doubts', value: health.open_doubts, href: '/devices?doubt=1' },
        { key: 'unproven', value: health.unproven },
        { key: 'range_only', value: health.range_only },
    ];

    return (
        <Layout title={t('health.title')} wide>
            <PageHeader title={t('health.title')} intro={t('health.intro')} />

            {!levels.known ? (
                <p className="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">{t('health.no_levels')}</p>
            ) : (
                <div className="mb-6 grid gap-4 md:grid-cols-2">
                    {[2, 3].map((n) => {
                        const value = n === 2 ? levels.level2 : levels.level3;
                        return (
                            <Card key={n} className="flex items-center gap-5 p-5">
                                <Ring value={value} total={totals.devices} />
                                <div className="min-w-0">
                                    <p className="text-sm text-ink-400">{t(`health.level${n}`)}</p>
                                    <p className="text-3xl font-semibold tabular-nums text-ink-100">
                                        {number(value)} <span className="text-sm font-normal text-ink-400">{t('health.of_total', { total: number(totals.devices) })}</span>
                                    </p>
                                    <p className="mt-1 text-xs text-ink-400">{t(`health.level${n}_text`)}</p>
                                    {value > 0 && (
                                        <Link href={`/devices?level=${n}`} className="mt-1 inline-block text-xs text-accent-300 underline">
                                            {t('common.see_all')}
                                        </Link>
                                    )}
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                <Card className="p-5">
                    <CardTitle>{t('health.missing')}</CardTitle>
                    <p className="mb-4 mt-1 text-sm text-ink-400">{t('health.missing_text')}</p>
                    <ol className="space-y-3">
                        {health.missing.map((m) => (
                            <li key={m.field}>
                                <div className="flex items-start justify-between gap-3 text-sm">
                                    <FieldName code={m.field} />
                                    <span className="flex shrink-0 items-center gap-2">
                                        <Badge tone={m.level === 2 ? 'accent' : 'neutral'}>{t('health.level_tag', { n: m.level })}</Badge>
                                    </span>
                                </div>
                                <div className="mt-1.5 flex items-center gap-3">
                                    <div className="h-1.5 flex-1 rounded-full bg-ink-750">
                                        <div className="h-1.5 rounded-full bg-amber-300/80" style={{ width: `${Math.round((m.missing / worst) * 100)}%` }} />
                                    </div>
                                    <span className="w-44 shrink-0 text-right text-xs tabular-nums text-ink-400 max-sm:w-auto">{t('health.missing_n', { n: number(m.missing) })}</span>
                                </div>
                            </li>
                        ))}
                    </ol>
                    <Link href="/contribute" className="mt-5 inline-flex rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-300">
                        {t('health.cta')}
                    </Link>
                </Card>
                <Card className="h-fit p-5">
                    <CardTitle className="mb-3">{t('health.signals')}</CardTitle>
                    <dl className="space-y-2">
                        {signals.map((s) => {
                            const body = (
                                <>
                                    <dt className="text-sm text-ink-300">{t(`health.${s.key}`)}</dt>
                                    <dd className="text-lg font-semibold tabular-nums text-ink-100">{number(s.value)}</dd>
                                </>
                            );
                            return s.href ? (
                                <Link key={s.key} href={s.href} className="flex items-center justify-between gap-3 rounded-lg bg-ink-800 px-3 py-2.5 hover:bg-ink-750">
                                    {body}
                                </Link>
                            ) : (
                                <div key={s.key} className="flex items-center justify-between gap-3 rounded-lg bg-ink-800 px-3 py-2.5">
                                    {body}
                                </div>
                            );
                        })}
                    </dl>
                </Card>
            </div>
        </Layout>
    );
}
