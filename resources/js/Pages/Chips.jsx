// SPDX-License-Identifier: Apache-2.0
import { Link } from '@inertiajs/react';
import Icon from '../Components/Icon';
import Layout from '../Components/Layout';
import { Card, CardTitle, Empty, ListRow, PageHeader } from '../Components/ui';
import { useT } from '../i18n';

export default function Chips({ families, unassigned }) {
    const { t } = useT();
    const max = Math.max(1, ...families.map((f) => f.devices));

    return (
        <Layout title={t('chips.title')} wide>
            <PageHeader title={t('chips.title')} intro={t('chips.intro')} />
            {families.length === 0 ? (
                <Empty>{t('common.empty')}</Empty>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {families.map((f) => (
                        <li key={f.id}>
                            <Link
                                href={`/devices?family=${encodeURIComponent(f.id)}`}
                                className="group block h-full rounded-xl border border-ink-700/60 bg-ink-850 p-4 transition-colors hover:border-accent-400/50 hover:bg-ink-800"
                            >
                                <div className="flex items-start gap-3">
                                    <Icon name="chips" className="mt-0.5 h-6 w-6 shrink-0 text-accent-400" strokeWidth={1.5} />
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium text-ink-100 group-hover:text-accent-300">{f.name}</p>
                                        <code className="text-xs text-ink-500">{f.id}</code>
                                    </div>
                                    <span className="text-sm tabular-nums text-ink-300">{t('common.devices_n', { n: f.devices })}</span>
                                </div>
                                <div className="mt-3 h-1.5 rounded-full bg-ink-750">
                                    <div className="h-1.5 rounded-full bg-accent-400" style={{ width: `${Math.max(2, Math.round((f.devices / max) * 100))}%` }} />
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            {unassigned.length > 0 && (
                <Card className="mt-6 p-5">
                    <CardTitle>{t('chips.unassigned')}</CardTitle>
                    <p className="mb-3 mt-1 text-sm text-ink-400">{t('chips.unassigned_text')}</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {unassigned.map((row) => (
                            <ListRow
                                key={row.id ?? 'none'}
                                label={row.id === null ? t('chips.not_set') : <code>{row.id}</code>}
                                value={t('common.devices_n', { n: row.devices })}
                                href={row.id ? `/devices?family=${encodeURIComponent(row.id)}` : undefined}
                            />
                        ))}
                    </div>
                </Card>
            )}
        </Layout>
    );
}
