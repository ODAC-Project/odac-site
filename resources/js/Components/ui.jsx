// SPDX-License-Identifier: Apache-2.0
import { Link } from '@inertiajs/react';
import { useT } from '../i18n';
import Icon from './Icon';

export function Card({ as: Tag = 'section', className = '', children, ...props }) {
    return (
        <Tag className={`rounded-xl border border-ink-700/60 bg-ink-850 ${className}`} {...props}>
            {children}
        </Tag>
    );
}

export function CardTitle({ children, className = '', as: Tag = 'h2' }) {
    return <Tag className={`text-base font-semibold text-ink-100 ${className}`}>{children}</Tag>;
}

export function PageHeader({ title, intro, children }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight text-ink-100 sm:text-3xl">{title}</h1>
                {intro && <p className="mt-1.5 max-w-3xl text-sm text-ink-400">{intro}</p>}
            </div>
            {children}
        </div>
    );
}

const BADGE = {
    neutral: 'bg-ink-750 text-ink-300 ring-ink-600/60',
    accent: 'bg-accent-400/10 text-accent-300 ring-accent-400/30',
    rose: 'bg-rose-400/10 text-rose-300 ring-rose-400/30',
    amber: 'bg-amber-400/10 text-amber-200 ring-amber-400/30',
};

export function Badge({ tone = 'neutral', children, className = '', title }) {
    return (
        <span title={title} className={`inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset ${BADGE[tone]} ${className}`}>
            {children}
        </span>
    );
}

export function KindBadge({ kind }) {
    const { t, has } = useT();
    return <Badge>{has(`kinds.${kind}`) ? t(`kinds.${kind}`) : kind}</Badge>;
}

export function LevelBadge({ level, long = false }) {
    const { t } = useT();
    if (level === null || level === undefined) {
        return <Badge title={t('levels.none')}>{t('levels.none')}</Badge>;
    }
    const tone = level >= 2 ? 'accent' : 'neutral';
    return (
        <Badge tone={tone} title={t(`levels.${level}`)}>
            {level === 0 ? t('levels.0') : t('levels.badge', { n: level })}
            {long && level > 0 && <span className="font-normal opacity-80">· {t(`levels.${level}`)}</span>}
        </Badge>
    );
}

export function Signals({ contested, doubt }) {
    const { t } = useT();
    return (
        <>
            {contested && <Badge tone="rose">{t('signals.contested')}</Badge>}
            {doubt && <Badge tone="amber">{t('signals.doubt')}</Badge>}
        </>
    );
}

/** A horizontal bar row: label, value, share of the largest. */
export function BarRow({ label, value, max, href, hint }) {
    const width = max > 0 ? Math.max(2, Math.round((value / max) * 100)) : 0;
    const { number } = useT();
    const content = (
        <>
            <div className="flex items-baseline justify-between gap-3 text-sm">
                <span className="min-w-0 truncate text-ink-200">{label}</span>
                <span className="shrink-0 tabular-nums text-ink-400">
                    {number(value)}
                    {hint && <span className="ml-1 text-ink-500">{hint}</span>}
                </span>
            </div>
            <div className="mt-1 h-1.5 rounded-full bg-ink-750">
                <div className="h-1.5 rounded-full bg-accent-400" style={{ width: `${width}%` }} />
            </div>
        </>
    );
    return href ? (
        <Link href={href} className="block rounded-md px-2 py-1.5 hover:bg-ink-800">
            {content}
        </Link>
    ) : (
        <div className="px-2 py-1.5">{content}</div>
    );
}

export function ListRow({ label, value, href }) {
    const inner = (
        <>
            <span className="min-w-0 truncate text-ink-200">{label}</span>
            <span className="shrink-0 text-xs tabular-nums text-ink-400">{value}</span>
        </>
    );
    const cls = 'flex items-center justify-between gap-3 rounded-lg bg-ink-800 px-3 py-2.5 text-sm';
    return href ? (
        <Link href={href} className={`${cls} hover:bg-ink-750`}>
            {inner}
        </Link>
    ) : (
        <div className={cls}>{inner}</div>
    );
}

export function SearchField({ value, onChange, placeholder, label, className = '', large = false, autoFocus = false }) {
    return (
        <label className={`relative block ${className}`}>
            <span className="sr-only">{label}</span>
            <Icon name="search" className={`pointer-events-none absolute top-1/2 -translate-y-1/2 text-ink-500 ${large ? 'left-4 h-5 w-5' : 'left-3 h-4 w-4'}`} />
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                autoFocus={autoFocus}
                className={`w-full rounded-lg border border-ink-600 bg-ink-900 text-ink-100 placeholder:text-ink-500 focus:border-accent-400 focus:outline-none focus:ring-1 focus:ring-accent-400 ${
                    large ? 'py-3 pl-12 pr-4 text-base' : 'py-2 pl-9 pr-3 text-sm'
                }`}
            />
        </label>
    );
}

export function Empty({ children }) {
    return <p className="rounded-xl border border-dashed border-ink-700 px-4 py-8 text-center text-sm text-ink-400">{children}</p>;
}
