// SPDX-License-Identifier: Apache-2.0
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { switchLocale, useT } from '../i18n';
import Icon from './Icon';

const NAV = [
    { key: 'home', href: '/', icon: 'home' },
    { key: 'why', href: '/pourquoi', icon: 'help' },
    { key: 'brands', href: '/brands', icon: 'brands' },
    { key: 'devices', href: '/devices', icon: 'devices' },
    { key: 'chips', href: '/chips', icon: 'chips' },
    { key: 'compare', href: '/compare', icon: 'compare' },
    { key: 'stats', href: '/stats', icon: 'stats' },
    { key: 'health', href: '/health', icon: 'health' },
    { key: 'contribute', href: '/contribute', icon: 'contribute' },
    { key: 'propose', href: '/proposer', icon: 'plus' },
    { key: 'help', href: '/help', icon: 'help' },
];

/** The text word-mark: no logo file, no borrowed mark. */
export function Wordmark({ className = 'text-2xl' }) {
    return (
        <span className={`font-semibold tracking-tight text-ink-100 ${className}`}>
            odac<span className="text-accent-400">.</span>
        </span>
    );
}

function isActive(url, href) {
    const path = url.split('?')[0];
    if (href === '/') return path === '/';
    if (href === '/devices') return path === '/devices' || /^\/[^/]+\/[^/]+$/.test(path);
    return path === href;
}

function Sidebar({ onNavigate }) {
    const page = usePage();
    const { url } = page;
    const { app, snapshot, locale, locales } = page.props;
    const { t, date } = useT();

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-ink-700/70 px-5 pb-4 pt-5">
                <Link href="/" onClick={onNavigate} className="block">
                    <Wordmark />
                    <span className="mt-0.5 block text-xs text-ink-400">{t('brand.tagline')}</span>
                </Link>
            </div>

            <nav aria-label={t('nav.main')} className="flex-1 overflow-y-auto px-3 py-4">
                <ul className="space-y-1">
                    {NAV.map((item) => {
                        const active = isActive(url, item.href);
                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    onClick={onNavigate}
                                    aria-current={active ? 'page' : undefined}
                                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors ${
                                        active
                                            ? 'bg-accent-400/15 font-medium text-accent-300 ring-1 ring-accent-400/30'
                                            : 'text-ink-300 hover:bg-ink-800 hover:text-ink-100'
                                    }`}
                                >
                                    <Icon name={item.icon} className="h-[18px] w-[18px] shrink-0" />
                                    {t(`nav.${item.key}`)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="space-y-1 border-t border-ink-700/70 px-3 py-3">
                <div className="flex items-center gap-3 px-3 py-1.5 text-sm text-ink-300">
                    <Icon name="language" className="h-[18px] w-[18px] shrink-0" />
                    <span className="sr-only">{t('nav.language')}</span>
                    <div className="flex rounded-md bg-ink-800 p-0.5" role="group" aria-label={t('nav.language')}>
                        {locales.map((code) => (
                            <button
                                key={code}
                                type="button"
                                lang={code}
                                aria-pressed={code === locale}
                                onClick={() => code !== locale && switchLocale(code)}
                                className={`rounded px-2.5 py-0.5 text-xs font-semibold uppercase ${
                                    code === locale ? 'bg-accent-400 text-ink-950' : 'text-ink-300 hover:text-ink-100'
                                }`}
                            >
                                {code}
                            </button>
                        ))}
                    </div>
                </div>
                <a
                    href={app.github_url}
                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-300 hover:bg-ink-800 hover:text-ink-100"
                    rel="noopener"
                >
                    <Icon name="github" className="h-[18px] w-[18px] shrink-0" />
                    {t('nav.github')}
                </a>
            </div>

            <div className="border-t border-ink-700/70 px-5 py-3 text-[11px] leading-relaxed text-ink-500">
                <p>{t('footer.licence')}</p>
                {snapshot?.date && <p>{t('footer.updated', { date: date(snapshot.date) })}</p>}
            </div>
        </div>
    );
}

export default function Layout({ title, children, wide = false }) {
    const { t } = useT();
    const { snapshot, locale } = usePage().props;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    useEffect(() => {
        if (!open) return undefined;
        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    return (
        <div className="min-h-screen">
            <Head title={title} />

            {/* Fixed sidebar from 900px; a drawer below. */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 border-r border-ink-700/70 bg-ink-900 min-[900px]:block">
                <Sidebar />
            </aside>

            <header className="sticky top-0 z-20 flex items-center justify-between border-b border-ink-700/70 bg-ink-900/95 px-4 py-3 backdrop-blur min-[900px]:hidden">
                <Link href="/" className="flex items-baseline gap-2">
                    <Wordmark className="text-xl" />
                    <span className="text-xs text-ink-400">{t('brand.tagline')}</span>
                </Link>
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="rounded-lg p-2 text-ink-300 hover:bg-ink-800 hover:text-ink-100"
                    aria-label={t('nav.menu')}
                    aria-expanded={open}
                    aria-controls="odac-drawer"
                >
                    <Icon name="menu" />
                </button>
            </header>

            {open && (
                <div className="fixed inset-0 z-40 min-[900px]:hidden" role="dialog" aria-modal="true" id="odac-drawer">
                    <button type="button" className="absolute inset-0 bg-ink-950/70" aria-label={t('nav.close')} onClick={() => setOpen(false)} />
                    <div className="absolute inset-y-0 left-0 w-72 max-w-[85vw] border-r border-ink-700 bg-ink-900 shadow-2xl">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="absolute right-3 top-4 rounded-lg p-1.5 text-ink-400 hover:bg-ink-800 hover:text-ink-100"
                            aria-label={t('nav.close')}
                        >
                            <Icon name="close" />
                        </button>
                        <Sidebar onNavigate={() => setOpen(false)} />
                    </div>
                </div>
            )}

            <div className="min-[900px]:pl-60">
                <main className={`mx-auto px-4 py-6 sm:px-6 lg:px-8 lg:py-8 ${wide ? 'max-w-[1400px]' : 'max-w-6xl'}`}>
                    {!snapshot && (
                        <p className="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">{t('common.no_snapshot')}</p>
                    )}
                    {children}
                </main>
                <footer className="mx-auto max-w-6xl px-4 pb-8 text-xs text-ink-500 sm:px-6 lg:px-8">
                    <div className="border-t border-ink-700/60 pt-4">
                        <p>{t('footer.quotes')}</p>
                        {snapshot && (
                            <p className="mt-1">
                                {t('footer.snapshot', { version: snapshot.version, fingerprint: snapshot.fingerprint })} ·{' '}
                                <a className="underline hover:text-ink-300" href="/latest.json">{t('footer.pointer')}</a>
                            </p>
                        )}
                    </div>
                </footer>
            </div>
        </div>
    );
}
