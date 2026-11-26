// SPDX-License-Identifier: Apache-2.0
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../Components/Layout';

const KINDS = { dac: 'Convertisseur', network_player: 'Lecteur réseau' };

export default function Index({ devices, filters, brands, snapshot }) {
    const [q, setQ] = useState(filters.q);

    const apply = (changes) => {
        const next = { ...filters, q, ...changes };
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== '' && v !== false && v !== null),
        );
        router.get('/', query, { preserveState: true, replace: true });
    };

    return (
        <Layout snapshot={snapshot}>
            <Head title="Appareils" />
            <form
                className="mb-4 flex flex-wrap items-end gap-3 text-sm"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply({});
                }}
            >
                <label className="flex flex-col">
                    Recherche (marque, modèle, alias)
                    <input className="rounded border border-stone-400 px-2 py-1" value={q} onChange={(e) => setQ(e.target.value)} />
                </label>
                <label className="flex flex-col">
                    Marque
                    <select className="rounded border border-stone-400 px-2 py-1" value={filters.brand} onChange={(e) => apply({ brand: e.target.value })}>
                        <option value="">toutes</option>
                        {brands.map((b) => (
                            <option key={b.brand_slug} value={b.brand_slug}>{b.brand_name}</option>
                        ))}
                    </select>
                </label>
                <label className="flex flex-col">
                    Type
                    <select className="rounded border border-stone-400 px-2 py-1" value={filters.kind} onChange={(e) => apply({ kind: e.target.value })}>
                        <option value="">tous</option>
                        {Object.entries(KINDS).map(([k, label]) => (
                            <option key={k} value={k}>{label}</option>
                        ))}
                    </select>
                </label>
                <label className="flex items-center gap-1">
                    <input type="checkbox" checked={filters.contested} onChange={(e) => apply({ contested: e.target.checked ? 1 : '' })} />
                    contesté
                </label>
                <label className="flex items-center gap-1">
                    <input type="checkbox" checked={filters.doubt} onChange={(e) => apply({ doubt: e.target.checked ? 1 : '' })} />
                    doute ouvert
                </label>
                <button className="rounded bg-stone-800 px-3 py-1 text-white" type="submit">Chercher</button>
            </form>

            <p className="mb-2 text-sm text-stone-600">
                {devices.total} appareil{devices.total > 1 ? 's' : ''}
            </p>

            <table className="w-full text-left text-sm">
                <thead className="border-b border-stone-300 text-stone-600">
                    <tr>
                        <th className="py-1">Marque</th>
                        <th>Modèle</th>
                        <th>Type</th>
                        <th>Niveau</th>
                        <th>Signaux</th>
                    </tr>
                </thead>
                <tbody>
                    {devices.data.map((d) => (
                        <tr key={d.odac_id} className="border-b border-stone-200">
                            <td className="py-1">{d.brand_name}</td>
                            <td>
                                <Link className="underline" href={`/${d.odac_id}`}>{d.model_name}</Link>
                            </td>
                            <td>{KINDS[d.kind] ?? d.kind}</td>
                            <td>{d.level ?? '—'}</td>
                            <td className="space-x-1">
                                {d.is_contested && <span className="rounded bg-rose-100 px-1 text-rose-900">contesté</span>}
                                {d.has_open_doubt && <span className="rounded bg-amber-100 px-1 text-amber-900">doute ouvert</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {devices.last_page > 1 && (
                <nav className="mt-4 flex flex-wrap gap-1 text-sm" aria-label="Pagination">
                    {devices.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                preserveScroll
                                className={`rounded border px-2 py-0.5 ${link.active ? 'border-stone-800 bg-stone-800 text-white' : 'border-stone-300'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span key={i} className="px-2 py-0.5 text-stone-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </nav>
            )}
        </Layout>
    );
}
