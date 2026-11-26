// SPDX-License-Identifier: Apache-2.0
import { Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import Icon from '../Components/Icon';
import Layout, { Wordmark } from '../Components/Layout';
import { Card, CardTitle, ListRow, SearchField } from '../Components/ui';
import { useT } from '../i18n';

const EXAMPLES = ['Qutest', 'Topping', 'ES9038'];

export default function Home({ totals, contributors, sources }) {
    const { t, number, locale } = useT();
    const [open, close] = locale === 'fr' ? ['« ', ' »'] : ['“', '”'];
    const [q, setQ] = useState('');

    const search = (term) => router.get('/devices', term ? { q: term } : {});

    const overview = [
        { key: 'brands', value: totals.brands, href: '/brands' },
        { key: 'devices', value: totals.devices, href: '/devices' },
        { key: 'claims', value: totals.claims, hint: t('home.claims_hint'), href: '/stats' },
        ...(totals.families > 0 ? [{ key: 'families', value: totals.families, href: '/chips' }] : []),
    ];

    const explore = [
        { key: 'find', icon: 'search', href: '/devices' },
        { key: 'brands', icon: 'brands', href: '/brands' },
        { key: 'stats', icon: 'stats', href: '/stats' },
        { key: 'contribute', icon: 'contribute', href: '/contribute' },
    ];

    const examples = EXAMPLES.map((e, i) => (
        <Fragment key={e}>
            {i > 0 && ', '}
            <button type="button" onClick={() => search(e)} className="text-ink-300 underline decoration-ink-600 hover:text-accent-300">
                {open}
                {e}
                {close}
            </button>
        </Fragment>
    ));
    const [before, after] = t('home.try', { examples: '\u0000' }).split('\u0000');

    return (
        <Layout wide>
            <div className="px-0 pb-8 pt-4 text-center sm:pt-8">
                <h1>
                    <Wordmark className="text-5xl sm:text-6xl" />
                    <span className="sr-only"> ODAC</span>
                </h1>
                <p className="mx-auto mt-4 max-w-2xl text-base text-ink-300 sm:text-lg">{t('home.promise')}</p>
                <form
                    role="search"
                    className="mx-auto mt-6 max-w-xl"
                    onSubmit={(e) => {
                        e.preventDefault();
                        search(q.trim());
                    }}
                >
                    <SearchField large value={q} onChange={setQ} label={t('home.search_label')} placeholder={t('home.search_placeholder')} />
                </form>
                <p className="mt-2 text-xs text-ink-500">
                    {before}
                    {examples}
                    {after}
                </p>
            </div>

            <Card className="px-4 py-6 text-center sm:px-6">
                <CardTitle>{t('home.overview')}</CardTitle>
                <dl className="mt-5 flex flex-wrap items-start justify-center gap-x-4 gap-y-4 sm:gap-x-8">
                    {overview.map((item, i) => (
                        <Fragment key={item.key}>
                            {i > 0 && <Icon name="arrow" className="mt-3 hidden h-5 w-5 text-ink-500 sm:block" />}
                            <Link href={item.href} className="flex min-w-[6.5rem] flex-col items-center rounded-lg px-3 py-1 hover:bg-ink-800">
                                <dt className="text-xs text-ink-400">
                                    {t(`home.${item.key}`)}
                                    {item.hint && <span className="block text-[11px] text-ink-500">{item.hint}</span>}
                                </dt>
                                <dd className="order-first text-3xl font-semibold tabular-nums text-ink-100">{number(item.value)}</dd>
                            </Link>
                        </Fragment>
                    ))}
                </dl>
            </Card>

            <h2 className="mb-3 mt-8 text-lg font-semibold text-ink-100">{t('home.explore')}</h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {explore.map((item) => (
                    <Link
                        key={item.key}
                        href={item.href}
                        className="group rounded-xl border border-ink-700/60 bg-ink-850 p-5 transition-colors hover:border-accent-400/50 hover:bg-ink-800"
                    >
                        <Icon name={item.icon} className="h-7 w-7 text-accent-400" strokeWidth={1.5} />
                        <h3 className="mt-3 font-medium text-ink-100 group-hover:text-accent-300">{t(`home.${item.key}_title`)}</h3>
                        <p className="mt-1 text-sm text-ink-400">{t(`home.${item.key}_text`)}</p>
                    </Link>
                ))}
            </div>

            <div className="mt-8 grid gap-4 lg:grid-cols-2">
                <Card className="p-5">
                    <CardTitle className="mb-3">{t('home.contributors')}</CardTitle>
                    {contributors.length === 0 ? (
                        <p className="text-sm text-ink-400">{t('common.empty')}</p>
                    ) : (
                        <div className="space-y-2">
                            {contributors.map((c) => (
                                <ListRow
                                    key={c.name}
                                    label={c.name === 'anonymous' ? t('common.anonymous') : c.name}
                                    value={t('common.claims_n', { n: c.claims })}
                                />
                            ))}
                        </div>
                    )}
                </Card>
                <Card className="p-5">
                    <CardTitle className="mb-3">{t('home.sources')}</CardTitle>
                    {sources.length === 0 ? (
                        <p className="text-sm text-ink-400">{t('common.empty')}</p>
                    ) : (
                        <div className="space-y-2">
                            {sources.map((s) => (
                                <ListRow key={s.host} label={s.host} value={t('common.claims_n', { n: s.claims })} />
                            ))}
                        </div>
                    )}
                </Card>
            </div>

            <Card className="mt-8 p-5 sm:p-6">
                <CardTitle className="mb-3">{t('home.about')}</CardTitle>
                <div className="space-y-3 text-sm leading-relaxed text-ink-300">
                    {[1, 2, 3, 4].map((n) => (
                        <p key={n}>{t(`home.about_${n}`)}</p>
                    ))}
                </div>
            </Card>
        </Layout>
    );
}
