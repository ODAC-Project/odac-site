// SPDX-License-Identifier: Apache-2.0
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ snapshot, children }) {
    const { app } = usePage().props;

    return (
        <div className="mx-auto max-w-5xl px-4 py-6">
            <header className="mb-6 flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-300 pb-3">
                <Link href="/" className="text-xl font-semibold">
                    ODAC <span className="font-normal text-stone-600">· registre ouvert des DAC</span>
                </Link>
                {app?.contribute_url && (
                    <a href={app.contribute_url} className="rounded border border-stone-400 px-3 py-1 text-sm hover:bg-stone-100">
                        Contribuer
                    </a>
                )}
            </header>
            <main>{children}</main>
            <footer className="mt-10 border-t border-stone-300 pt-3 text-xs text-stone-600">
                <p>
                    Les faits sont publiés sous licence CC BY-SA 4.0. Les citations (<code>source.quote</code>) restent la
                    propriété de leurs auteurs et ne sont reproduites qu'à titre de preuve.
                </p>
                {snapshot && (
                    <p className="mt-1">
                        Instantané {snapshot.version}, empreinte <code>{snapshot.fingerprint}</code> ·{' '}
                        <a className="underline" href="/latest.json">latest.json</a>
                    </p>
                )}
            </footer>
        </div>
    );
}
