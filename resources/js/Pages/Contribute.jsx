// SPDX-License-Identifier: Apache-2.0
import { usePage } from '@inertiajs/react';
import FieldName from '../Components/FieldName';
import Icon from '../Components/Icon';
import Layout from '../Components/Layout';
import { Badge, Card, CardTitle, PageHeader } from '../Components/ui';
import { useT } from '../i18n';

export default function Contribute({ missing }) {
    const { app } = usePage().props;
    const { t, number } = useT();
    const repo = app.github_url;

    return (
        <Layout title={t('contribute.title')}>
            <PageHeader title={t('contribute.title')} intro={t('contribute.intro')} />

            <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                <Card className="p-5 sm:p-6">
                    <CardTitle>{t('contribute.today')}</CardTitle>
                    <ol className="mt-4 space-y-4">
                        {[1, 2, 3, 4].map((n) => (
                            <li key={n} className="flex gap-3 text-sm text-ink-300">
                                <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-400/15 text-xs font-semibold text-accent-300">{n}</span>
                                <span className="pt-0.5 leading-relaxed">{t(`contribute.step_${n}`)}</span>
                            </li>
                        ))}
                    </ol>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <a href={`${repo}/blob/main/docs/CONTRIBUTING.md`} rel="noopener" className="inline-flex items-center gap-2 rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-300">
                            {t('contribute.guide')}
                            <Icon name="external" className="h-4 w-4" />
                        </a>
                        <a href={repo} rel="noopener" className="inline-flex items-center gap-2 rounded-lg border border-ink-600 px-4 py-2 text-sm text-ink-200 hover:border-accent-400/60 hover:text-accent-300">
                            <Icon name="github" className="h-4 w-4" />
                            {t('contribute.repo')}
                        </a>
                    </div>
                    <div className="mt-6 rounded-lg bg-ink-800 p-4">
                        <h3 className="text-sm font-semibold text-ink-100">{t('contribute.dco')}</h3>
                        <p className="mt-1 text-sm text-ink-300">{t('contribute.dco_text')}</p>
                        <pre className="mt-3 overflow-x-auto rounded-md bg-ink-950 px-3 py-2 text-xs text-ink-300">
                            <code>git commit -s</code>
                        </pre>
                    </div>
                </Card>

                <div className="space-y-4">
                    <Card className="p-5">
                        <CardTitle>{t('contribute.rules')}</CardTitle>
                        <ul className="mt-3 space-y-2">
                            {[1, 2, 3].map((n) => (
                                <li key={n} className="flex gap-2 text-sm text-ink-300">
                                    <Icon name="check" className="mt-0.5 h-4 w-4 shrink-0 text-accent-400" />
                                    {t(`contribute.rule_${n}`)}
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card className="border-dashed p-5">
                        <div className="flex items-center gap-2">
                            <CardTitle>{t('contribute.soon')}</CardTitle>
                        </div>
                        <p className="mt-1 text-sm text-ink-400">{t('contribute.soon_text')}</p>
                    </Card>
                    {missing.length > 0 && (
                        <Card className="p-5">
                            <CardTitle>{t('contribute.missing')}</CardTitle>
                            <ul className="mt-3 space-y-2">
                                {missing.map((m) => (
                                    <li key={m.field} className="flex items-start justify-between gap-3 rounded-lg bg-ink-800 px-3 py-2 text-sm">
                                        <FieldName code={m.field} />
                                        <Badge tone="amber">{t('health.missing_n', { n: number(m.missing) })}</Badge>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>
            </div>
        </Layout>
    );
}
