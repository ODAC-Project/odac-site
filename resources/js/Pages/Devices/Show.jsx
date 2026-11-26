// SPDX-License-Identifier: Apache-2.0
import { Head, usePage } from '@inertiajs/react';
import Layout from '../../Components/Layout';
import Value from '../../Components/Value';

const KINDS = { dac: 'Convertisseur', network_player: 'Lecteur réseau' };
const SOURCE_TYPES = { datasheet: 'fiche technique', manual: 'manuel', retailer: 'revendeur', import: 'import', probe: 'sonde', measurement: 'mesure' };
const RELATIONS = { spelling: 'graphie', edition: 'édition', neighbour: 'voisin : un autre produit' };

function Source({ source }) {
    return (
        <div className="text-sm">
            <span className="font-medium">{SOURCE_TYPES[source.type] ?? source.type ?? 'source'}</span>
            {source.read_on && <span className="text-stone-600">, lue le {source.read_on}</span>}
            {source.url && (
                <>
                    {' · '}
                    <a className="break-all underline" href={source.url} rel="nofollow noopener" target="_blank">{source.url}</a>
                </>
            )}
            {source.names_subject === false && (
                <p className="mt-1 rounded bg-rose-50 px-2 py-1 text-rose-900">
                    Cette source décrit la gamme, pas ce modèle.
                </p>
            )}
            {source.quote && <blockquote className="mt-1 border-l-4 border-stone-300 pl-3 italic">« {source.quote} »</blockquote>}
        </div>
    );
}

export default function Show({ sheet, snapshot }) {
    const { app } = usePage().props;
    const { identity, proofs, summary, open_doubt: doubt } = sheet;

    return (
        <Layout snapshot={snapshot}>
            <Head title={`${identity.brand} ${identity.model}`} />
            <h1 className="text-2xl font-semibold">
                {identity.brand} {identity.model}
            </h1>
            <p className="text-sm text-stone-600">
                {KINDS[identity.kind] ?? identity.kind} · niveau atteint : {identity.level ?? 'non établi'} · identifiant{' '}
                <code>{identity.odac_id}</code>
            </p>
            {identity.aliases.length > 0 && (
                <p className="mt-1 text-sm text-stone-600">
                    Aussi appelé :{' '}
                    {identity.aliases.map((a, i) => (
                        <span key={i}>
                            {i > 0 && ', '}
                            {a.name} <span className="text-stone-500">({RELATIONS[a.relation] ?? a.relation})</span>
                        </span>
                    ))}
                </p>
            )}

            {doubt && (
                <section className="mt-4 rounded border border-amber-300 bg-amber-50 p-3">
                    <h2 className="font-semibold">Une question ouverte</h2>
                    <p>{doubt.text}</p>
                    {app?.contribute_url && (
                        <a className="mt-1 inline-block text-sm underline" href={app.contribute_url}>Vous avez la réponse ? Contribuer</a>
                    )}
                </section>
            )}

            <section className="mt-6">
                <h2 className="mb-2 text-lg font-semibold">Récapitulatif</h2>
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-stone-300 text-stone-600">
                        <tr>
                            <th className="py-1">Champ</th>
                            <th>Valeur</th>
                            <th>Preuve</th>
                        </tr>
                    </thead>
                    <tbody>
                        {summary.map((row) => (
                            <tr key={row.field} className="border-b border-stone-200 align-top">
                                <td className="py-1 font-mono text-xs">{row.field}</td>
                                <td>
                                    {row.contested ? (
                                        <div>
                                            <span className="rounded bg-rose-100 px-1 text-rose-900">contesté</span>
                                            <ul className="mt-1 space-y-2">
                                                {row.contested.map((c, i) => (
                                                    <li key={i} className={c.chosen ? 'font-medium' : ''}>
                                                        <Value field={row.field} value={c.value} />
                                                        {c.chosen && <span className="ml-1 text-xs text-stone-600">(retenue par la construction)</span>}
                                                        {c.weight != null && <span className="ml-1 text-xs text-stone-500">poids {c.weight}</span>}
                                                        <Source source={c.source} />
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ) : (
                                        <Value field={row.field} value={row.value} />
                                    )}
                                </td>
                                <td>
                                    {row.proof ? (
                                        <a className="underline" href={`#preuve-${row.proof}`}>n° {row.proof}</a>
                                    ) : (
                                        !row.contested && <span className="text-rose-800">sans preuve</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mt-6">
                <h2 className="mb-2 text-lg font-semibold">Les preuves</h2>
                {proofs.length === 0 && <p className="text-sm text-stone-600">Aucune preuve publiée pour cet appareil.</p>}
                <ol className="space-y-4">
                    {proofs.map((p) => (
                        <li key={p.number} id={`preuve-${p.number}`} className="rounded border border-stone-300 p-3">
                            <p className="mb-1 text-xs text-stone-500">
                                Preuve n° {p.number}
                                {p.claim && <> · revendication <code>{p.claim}</code></>}
                                {p.weight != null && <> · poids {p.weight}</>} · soumise par {p.submitted_by === 'anonymous' ? 'anonyme' : p.submitted_by}
                                {p.submitted_on && <> le {p.submitted_on}</>}
                            </p>
                            <Source source={p.source} />
                            {p.fields.length > 0 && (
                                <ul className="mt-2 text-sm">
                                    {p.fields.map((f) => (
                                        <li key={f.field}>
                                            établit <code className="text-xs">{f.field}</code> = <Value field={f.field} value={f.value} />
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
