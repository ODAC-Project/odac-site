// SPDX-License-Identifier: Apache-2.0
import { Link, usePage } from '@inertiajs/react';
import Icon from '../../Components/Icon';
import Layout from '../../Components/Layout';
import { Badge, Card, CardTitle, IdentityBadge, KindBadge, LevelBadge } from '../../Components/ui';
import FieldName from '../../Components/FieldName';
import Value from '../../Components/Value';
import { useT } from '../../i18n';

// The facts a reader looks for first, when the record carries them.
const KEY_FACTS = ['pcm_max_hz', 'dsd_max', 'native_dsd', 'dop', 'bits', 'chip', 'family', 'technology'];

/**
 * A `quote` field is the contributor's account of the page: only the runs
 * inside « » are the source's own words (CONTRIBUTING, 300 characters each).
 * So the account is plain text and only those runs are set as quotation -
 * wrapping the whole field in quotation marks would present our prose as the
 * maker's, the very line the licence (facts vs quotations) draws.
 */
export function Quote({ text }) {
    const parts = text.split(/(«[^«»]*»)/g).filter((part) => part !== '');
    return (
        <p className="mt-2 break-words border-l-2 border-ink-600 pl-3 text-sm leading-relaxed text-ink-300">
            {parts.map((part, i) =>
                part.startsWith('«') ? (
                    <q key={i} className="italic text-ink-100 before:content-none after:content-none">
                        {part}
                    </q>
                ) : (
                    <span key={i}>{part}</span>
                ),
            )}
        </p>
    );
}

function Source({ source, compact = false, proofNumber = null }) {
    const { t, has, date } = useT();
    const type = source.type ? (has(`sheet.source_types.${source.type}`) ? t(`sheet.source_types.${source.type}`) : source.type) : t('sheet.source');
    return (
        <div className="text-sm">
            <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <Badge>{type}</Badge>
                {source.read_on && <span className="text-xs text-ink-400">{t('sheet.read_on', { date: date(source.read_on) })}</span>}
                {source.url && (
                    <a className="inline-flex min-w-0 items-center gap-1 break-all text-xs text-accent-300 underline decoration-accent-400/40 hover:decoration-accent-300" href={source.url} rel="nofollow noopener" target="_blank">
                        {source.url}
                        <Icon name="external" className="h-3 w-3 shrink-0" />
                    </a>
                )}
            </div>
            {source.names_subject === false && (
                <p className="mt-2 flex items-start gap-2 rounded-lg bg-rose-400/10 px-3 py-2 text-xs text-rose-200 ring-1 ring-inset ring-rose-400/30">
                    <Icon name="alert" className="mt-px h-4 w-4 shrink-0" />
                    {t('sheet.range_warning')}
                </p>
            )}
            {compact && proofNumber !== null ? (
                // Already shown in full under « Les preuves »: a link, not a second copy.
                <a className="mt-1 inline-block text-xs text-accent-300 underline decoration-accent-400/40" href={`#preuve-${proofNumber}`}>
                    {t('sheet.see_proof', { n: proofNumber })}
                </a>
            ) : (
                source.quote && <Quote text={source.quote} />
            )}
        </div>
    );
}

function Contested({ row, familyNames, proofOf }) {
    const { t } = useT();
    return (
        <div>
            <Badge tone="rose">{t('signals.contested')}</Badge>
            <ul className="mt-2 space-y-3">
                {row.contested.map((c, i) => (
                    <li key={i} className={`rounded-lg p-3 ${c.chosen ? 'bg-ink-800 ring-1 ring-inset ring-accent-400/30' : 'bg-ink-800/60'}`}>
                        <div className="flex flex-wrap items-baseline gap-2">
                            <span className={c.chosen ? 'font-semibold text-ink-100' : 'text-ink-200'}>
                                <Value field={row.field} value={c.value} familyNames={familyNames} />
                            </span>
                            {c.chosen && <Badge tone="accent">{t('sheet.chosen')}</Badge>}
                            {c.weight != null && <span className="text-xs text-ink-400">{t('sheet.weight', { w: c.weight })}</span>}
                            {c.claims > 1 && <span className="text-xs text-ink-400">{t('common.claims_n', { n: c.claims })}</span>}
                        </div>
                        <div className="mt-2">
                            <Source source={c.source} compact proofNumber={proofOf(c.claim)} />
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function Show({ sheet, family_names: familyNames, completeness }) {
    const { app } = usePage().props;
    const { t, date } = useT();
    const { identity, proofs, summary, open_doubt: doubt } = sheet;
    const rows = Object.fromEntries(summary.map((r) => [r.field, r]));
    // A key fact is short; a long text (a chip described in a sentence) stays in the summary.
    const keyFacts = KEY_FACTS.filter(
        (f) => rows[f] && (rows[f].contested || (rows[f].value !== null && !(typeof rows[f].value === 'string' && rows[f].value.length > 40))),
    );
    const contributeHref = app.contribute_url ?? '/contribute';
    const proofNumbers = Object.fromEntries(proofs.filter((p) => p.claim).map((p) => [p.claim, p.number]));
    const proofOf = (claim) => (claim && proofNumbers[claim] !== undefined ? proofNumbers[claim] : null);

    const nextLevel =
        completeness &&
        (completeness.level2_missing.length > 0
            ? { n: 2, missing: completeness.level2_missing }
            : completeness.level3_missing.length > 0
              ? { n: 3, missing: completeness.level3_missing }
              : null);

    return (
        <Layout title={`${identity.brand} ${identity.model}`}>
            <nav className="mb-4 text-sm text-ink-400">
                <Link href="/devices" className="hover:text-ink-200">
                    {t('nav.devices')}
                </Link>
                <span className="mx-2 text-ink-600">/</span>
                <Link href={`/devices?brand=${encodeURIComponent(identity.brand_slug)}`} className="hover:text-ink-200">
                    {identity.brand}
                </Link>
            </nav>

            <Card className="p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className="text-sm uppercase tracking-wide text-ink-400">{identity.brand}</p>
                        <h1 className="mt-0.5 text-2xl font-semibold tracking-tight text-ink-100 sm:text-3xl">{identity.model}</h1>
                        <div className="mt-3 flex flex-wrap gap-1.5">
                            <KindBadge kind={identity.kind} />
                            <IdentityBadge identified={identity.identified} />
                            <LevelBadge level={identity.level} long />
                            {summary.some((r) => r.contested) && <Badge tone="rose">{t('signals.contested')}</Badge>}
                            {doubt && <Badge tone="amber">{t('signals.doubt')}</Badge>}
                        </div>
                    </div>
                    <Link
                        href={`/compare?d[]=${encodeURIComponent(identity.odac_id)}`}
                        className="inline-flex items-center gap-2 rounded-lg border border-ink-600 px-3 py-2 text-sm text-ink-200 hover:border-accent-400/60 hover:text-accent-300"
                    >
                        <Icon name="compare" className="h-4 w-4" />
                        {t('sheet.compare')}
                    </Link>
                </div>
                <dl className="mt-4 grid gap-x-6 gap-y-2 border-t border-ink-700/60 pt-4 text-sm sm:grid-cols-[auto_1fr]">
                    <dt className="text-ink-400">{t('sheet.id')}</dt>
                    <dd>
                        <code className="text-ink-200">{identity.odac_id}</code>
                    </dd>
                    {identity.aliases.length > 0 && (
                        <>
                            <dt className="text-ink-400">{t('sheet.also_known')}</dt>
                            <dd className="text-ink-200">
                                {identity.aliases.map((a, i) => (
                                    <span key={i}>
                                        {i > 0 && ', '}
                                        {a.name} <span className="text-ink-500">({t(`sheet.relations.${a.relation}`)})</span>
                                    </span>
                                ))}
                            </dd>
                        </>
                    )}
                    {identity.usb_ids.length > 0 && (
                        <>
                            <dt className="text-ink-400">{t('sheet.usb')}</dt>
                            <dd>
                                {identity.usb_ids.map((u, i) => (
                                    <code key={i} className="mr-2 text-ink-200">
                                        {u.vid}:{u.pid}
                                    </code>
                                ))}
                            </dd>
                        </>
                    )}
                </dl>
            </Card>

            {doubt && (
                <section className="mt-4 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 sm:p-5">
                    <h2 className="flex flex-wrap items-baseline gap-x-2 font-semibold text-amber-100">
                        <Icon name="help" className="h-5 w-5 self-center text-amber-300" />
                        {t('sheet.doubt_title')}
                        {doubt.raised_on && <span className="text-sm font-normal text-amber-200/70">{t('sheet.raised_on', { date: date(doubt.raised_on) })}</span>}
                    </h2>
                    <p className="mt-2 text-sm text-amber-50/90">{doubt.text}</p>
                    <a className="mt-2 inline-block text-sm text-amber-200 underline" href={contributeHref}>
                        {t('sheet.doubt_cta')}
                    </a>
                </section>
            )}

            {keyFacts.length > 0 && (
                <section className="mt-4">
                    <h2 className="sr-only">{t('sheet.key_facts')}</h2>
                    <dl className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {keyFacts.map((f) => (
                            <div key={f} className="rounded-xl border border-ink-700/60 bg-ink-850 p-4">
                                <dt className="text-xs text-ink-400">{t(`fields.${f}`)}</dt>
                                <dd className="mt-1 break-words text-base font-semibold text-ink-100">
                                    {rows[f].contested ? <Badge tone="rose">{t('signals.contested')}</Badge> : <Value field={f} value={rows[f].value} familyNames={familyNames} />}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}

            {(nextLevel || !identity.identified) && (
                <Card className="mt-4 p-4 sm:p-5">
                    <CardTitle as="h2" className="text-sm">
                        {t('sheet.completeness')}
                    </CardTitle>
                    {!identity.identified && <p className="mt-1 text-sm text-ink-300">{t('sheet.missing_identity')}</p>}
                    {nextLevel && (
                        <>
                            <p className="mt-2 text-sm text-ink-300">{t('sheet.missing_for', { n: nextLevel.n })}</p>
                            <ul className="mt-2 flex flex-wrap gap-1.5">
                                {nextLevel.missing.map((f) => (
                                    <li key={f}>
                                        <Badge tone="amber">
                                            <code>{f}</code>
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                    <p className="mt-2 text-xs text-ink-400">
                        {t('sheet.help_level')}{' '}
                        <a href={contributeHref} className="text-accent-300 underline">
                            {t('nav.contribute')}
                        </a>
                    </p>
                </Card>
            )}

            <Card className="mt-6 overflow-hidden">
                <CardTitle className="border-b border-ink-700/60 px-5 py-4">{t('sheet.summary')}</CardTitle>
                {/* A grid, not a table: on a phone each row stacks instead of scrolling sideways. */}
                <div className="hidden grid-cols-[minmax(0,2fr)_minmax(0,3fr)_6rem] bg-ink-800/60 px-5 py-2 text-xs font-medium uppercase tracking-wide text-ink-400 sm:grid" aria-hidden="true">
                    <span>{t('sheet.field')}</span>
                    <span className="px-3">{t('sheet.value')}</span>
                    <span className="text-right">{t('sheet.proof')}</span>
                </div>
                <dl className="divide-y divide-ink-700/50 text-sm">
                    {summary.map((row) => (
                        <div key={row.field} className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1.5 px-5 py-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_6rem]">
                            <dt className="min-w-0">
                                <FieldName code={row.field} />
                            </dt>
                            <dd className="col-span-2 row-start-2 min-w-0 text-ink-200 sm:col-span-1 sm:row-start-auto sm:px-3">
                                {row.contested ? <Contested row={row} familyNames={familyNames} proofOf={proofOf} /> : <Value field={row.field} value={row.value} familyNames={familyNames} />}
                            </dd>
                            <dd className="col-start-2 row-start-1 whitespace-nowrap text-right sm:col-start-auto sm:row-start-auto">
                                {row.proof ? (
                                    <a className="text-accent-300 underline decoration-accent-400/40" href={`#preuve-${row.proof}`}>
                                        {t('sheet.proof_n', { n: row.proof })}
                                    </a>
                                ) : (
                                    !row.contested && <span className="text-rose-300">{t('sheet.no_proof')}</span>
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </Card>

            <section className="mt-6">
                <h2 className="mb-3 text-lg font-semibold text-ink-100">{t('sheet.proofs')}</h2>
                {proofs.length === 0 && <p className="text-sm text-ink-400">{t('sheet.no_proofs')}</p>}
                <ol className="space-y-3">
                    {proofs.map((p) => (
                        <li key={p.number} id={`preuve-${p.number}`} className="scroll-mt-20 rounded-xl border border-ink-700/60 bg-ink-850 p-4 sm:p-5">
                            <p className="mb-3 flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xs text-ink-400">
                                <span className="rounded-md bg-accent-400/15 px-2 py-0.5 font-semibold text-accent-300">{t('sheet.proof_header', { n: p.number })}</span>
                                {p.claim && (
                                    <span>
                                        {t('sheet.claim')} <code className="text-ink-300">{p.claim}</code>
                                    </span>
                                )}
                                {p.weight != null && <span>· {t('sheet.weight', { w: p.weight })}</span>}
                                <span>
                                    · {t('sheet.submitted_by', { who: p.submitted_by === 'anonymous' ? t('common.anonymous') : p.submitted_by })}
                                    {p.submitted_on && <> {t('sheet.submitted_on', { date: date(p.submitted_on) })}</>}
                                </span>
                            </p>
                            <Source source={p.source} />
                            {p.fields.length > 0 && (
                                <ul className="mt-3 grid gap-2 border-t border-ink-700/50 pt-3 text-sm sm:grid-cols-2">
                                    {p.fields.map((f) => (
                                        <li key={f.field} className="rounded-lg bg-ink-800 px-3 py-2">
                                            <span className="text-xs text-ink-400">{t('sheet.establishes')}</span>
                                            <FieldName code={f.field} />
                                            <span className="mt-1 block font-medium text-ink-100">
                                                <Value field={f.field} value={f.value} familyNames={familyNames} />
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>
            </section>
        </Layout>
    );
}
