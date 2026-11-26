// SPDX-License-Identifier: Apache-2.0
import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Layout from '../Components/Layout';
import { Badge, Empty, PageHeader, SearchField } from '../Components/ui';
import { useT } from '../i18n';

export default function Brands({ brands }) {
    const { t } = useT();
    const [q, setQ] = useState('');
    const shown = useMemo(() => {
        const needle = q.trim().toLocaleLowerCase();
        return needle ? brands.filter((b) => b.name.toLocaleLowerCase().includes(needle) || b.slug.includes(needle)) : brands;
    }, [brands, q]);

    return (
        <Layout title={t('brands.title')} wide>
            <PageHeader title={t('brands.title')} intro={t('brands.intro')}>
                <SearchField className="w-full sm:w-72" value={q} onChange={setQ} label={t('brands.filter_label')} placeholder={t('brands.filter_placeholder')} />
            </PageHeader>
            <p className="mb-3 text-sm text-ink-400" aria-live="polite">
                {t('brands.count', { n: shown.length })}
            </p>
            {shown.length === 0 ? (
                <Empty>{t('brands.empty')}</Empty>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    {shown.map((b) => (
                        <li key={b.slug}>
                            <Link
                                href={`/devices?brand=${encodeURIComponent(b.slug)}`}
                                className="group flex h-full items-center justify-between gap-3 rounded-xl border border-ink-700/60 bg-ink-850 px-4 py-3 transition-colors hover:border-accent-400/50 hover:bg-ink-800"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium text-ink-100 group-hover:text-accent-300">{b.name}</span>
                                    <span className="text-xs text-ink-400">{t('common.devices_n', { n: b.devices })}</span>
                                </span>
                                {b.level2 > 0 && <Badge tone="accent">{t('brands.level2', { n: b.level2 })}</Badge>}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Layout>
    );
}
