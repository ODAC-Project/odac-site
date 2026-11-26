// SPDX-License-Identifier: Apache-2.0
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import Layout from '../../Components/Layout';
import { Badge, Empty, IdentityBadge, KindBadge, LevelBadge, PageHeader, SearchField, Signals } from '../../Components/ui';
import { useT } from '../../i18n';

const KINDS = ['dac', 'network_player'];
const SELECT =
    'rounded-lg border border-ink-600 bg-ink-900 py-2 pl-3 pr-8 text-sm text-ink-100 focus:border-accent-400 focus:outline-none focus:ring-1 focus:ring-accent-400';

function Toggle({ checked, onChange, children }) {
    return (
        <label
            className={`inline-flex cursor-pointer select-none items-center gap-2 rounded-lg border px-3 py-2 text-sm ${
                checked ? 'border-accent-400/50 bg-accent-400/10 text-accent-300' : 'border-ink-600 bg-ink-900 text-ink-300 hover:text-ink-100'
            }`}
        >
            <input type="checkbox" className="sr-only" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span className={`h-3 w-3 rounded-sm border ${checked ? 'border-accent-400 bg-accent-400' : 'border-ink-500'}`} aria-hidden="true" />
            {children}
        </label>
    );
}

export default function Index({ devices, filters, brands }) {
    const { t, number } = useT();
    const [q, setQ] = useState(filters.q);

    const apply = (changes) => {
        const next = { ...filters, q, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== false && v !== null));
        router.get('/devices', query, { preserveState: true, preserveScroll: true, replace: true });
    };
    const filtered = Object.entries(filters).some(([, v]) => v !== '' && v !== false);

    return (
        <Layout title={t('devices.title')} wide>
            <PageHeader title={t('devices.title')} intro={t('devices.intro')} />

            <form
                role="search"
                className="mb-5 rounded-xl border border-ink-700/60 bg-ink-850 p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply({});
                }}
            >
                <div className="flex flex-col gap-3 lg:flex-row">
                    <SearchField className="flex-1" value={q} onChange={setQ} label={t('devices.search_label')} placeholder={t('devices.search_placeholder')} />
                    <button type="submit" className="rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-300">
                        {t('devices.submit')}
                    </button>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <label>
                        <span className="sr-only">{t('devices.brand')}</span>
                        <select className={`${SELECT} max-w-[14rem]`} value={filters.brand} onChange={(e) => apply({ brand: e.target.value })}>
                            <option value="">{t('devices.all_brands')}</option>
                            {brands.map((b) => (
                                <option key={b.brand_slug} value={b.brand_slug}>
                                    {b.brand_name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">{t('devices.kind')}</span>
                        <select className={SELECT} value={filters.kind} onChange={(e) => apply({ kind: e.target.value })}>
                            <option value="">{t('devices.all_kinds')}</option>
                            {KINDS.map((k) => (
                                <option key={k} value={k}>
                                    {t(`kinds.${k}`)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">{t('devices.level')}</span>
                        <select className={SELECT} value={filters.level} onChange={(e) => apply({ level: e.target.value })}>
                            <option value="">{t('devices.all_levels')}</option>
                            {['2', '3'].map((n) => (
                                <option key={n} value={n}>
                                    {t('levels.at_least', { n })}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">{t('devices.identity')}</span>
                        <select className={SELECT} value={filters.identified} onChange={(e) => apply({ identified: e.target.value })}>
                            <option value="">{t('devices.all_identity')}</option>
                            <option value="1">{t('devices.identified_only')}</option>
                            <option value="0">{t('devices.unidentified_only')}</option>
                        </select>
                    </label>
                    <Toggle checked={filters.contested} onChange={(v) => apply({ contested: v ? 1 : '' })}>
                        {t('devices.contested')}
                    </Toggle>
                    <Toggle checked={filters.doubt} onChange={(v) => apply({ doubt: v ? 1 : '' })}>
                        {t('devices.doubt')}
                    </Toggle>
                    {filters.family && (
                        <Badge tone="accent" className="py-1.5 text-xs">
                            {t('devices.family', { name: filters.family })}
                            <button type="button" onClick={() => apply({ family: '' })} aria-label={t('devices.reset')} className="ml-1">
                                <Icon name="close" className="h-3.5 w-3.5" />
                            </button>
                        </Badge>
                    )}
                    {filtered && (
                        <Link href="/devices" className="ml-auto text-sm text-ink-400 underline hover:text-ink-200">
                            {t('devices.reset')}
                        </Link>
                    )}
                </div>
            </form>

            <p className="mb-3 text-sm text-ink-400" aria-live="polite">
                {t('common.devices_n', { n: devices.total })}
            </p>

            {devices.data.length === 0 ? (
                <Empty>{t('devices.empty')}</Empty>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {devices.data.map((d) => (
                        <li key={d.odac_id}>
                            <Link
                                href={`/${d.odac_id}`}
                                className="group flex h-full flex-col rounded-xl border border-ink-700/60 bg-ink-850 p-4 transition-colors hover:border-accent-400/50 hover:bg-ink-800"
                            >
                                <span className="text-xs uppercase tracking-wide text-ink-400">{d.brand_name}</span>
                                <span className="mt-0.5 font-medium text-ink-100 group-hover:text-accent-300">{d.model_name}</span>
                                <span className="mt-3 flex flex-wrap gap-1.5">
                                    <KindBadge kind={d.kind} />
                                    {/* Only the exception is marked: almost every record is still unidentified. */}
                                    {d.identified && <IdentityBadge identified />}
                                    <LevelBadge level={d.level} />
                                    <Signals contested={d.is_contested} doubt={d.has_open_doubt} />
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {devices.last_page > 1 && (
                <nav className="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label={t('devices.pagination')}>
                    <span className="text-ink-400">{t('devices.page_of', { page: number(devices.current_page), last: number(devices.last_page) })}</span>
                    <div className="flex flex-wrap gap-1">
                        {devices.prev_page_url && (
                            <Link href={devices.prev_page_url} preserveScroll className="rounded-lg border border-ink-600 px-3 py-1.5 text-ink-300 hover:bg-ink-800">
                                {t('devices.prev')}
                            </Link>
                        )}
                        {devices.links.slice(1, -1).map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    aria-current={link.active ? 'page' : undefined}
                                    className={`min-w-[2.25rem] rounded-lg border px-3 py-1.5 text-center ${
                                        link.active ? 'border-accent-400 bg-accent-400 font-semibold text-ink-950' : 'border-ink-600 text-ink-300 hover:bg-ink-800'
                                    }`}
                                >
                                    {link.label}
                                </Link>
                            ) : (
                                <span key={i} className="px-2 py-1.5 text-ink-500">
                                    {link.label}
                                </span>
                            ),
                        )}
                        {devices.next_page_url && (
                            <Link href={devices.next_page_url} preserveScroll className="rounded-lg border border-ink-600 px-3 py-1.5 text-ink-300 hover:bg-ink-800">
                                {t('devices.next')}
                            </Link>
                        )}
                    </div>
                </nav>
            )}
        </Layout>
    );
}
