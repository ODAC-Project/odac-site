// SPDX-License-Identifier: Apache-2.0
import Layout from '../Components/Layout';
import { PageHeader } from '../Components/ui';
import { useT } from '../i18n';

const QUESTIONS = [1, 2, 3, 4, 5, 6, 7];

export default function Help() {
    const { t } = useT();
    return (
        <Layout title={t('help.title')}>
            <PageHeader title={t('help.title')} intro={t('help.intro')} />
            <div className="space-y-3">
                {QUESTIONS.map((n) => (
                    <details key={n} className="group rounded-xl border border-ink-700/60 bg-ink-850 open:bg-ink-800/80" open={n === 1}>
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-medium text-ink-100 [&::-webkit-details-marker]:hidden">
                            {t(`help.q${n}`)}
                            <span className="text-accent-400 transition-transform group-open:rotate-45" aria-hidden="true">
                                +
                            </span>
                        </summary>
                        <p className="px-5 pb-4 text-sm leading-relaxed text-ink-300">{t(`help.a${n}`)}</p>
                    </details>
                ))}
            </div>
        </Layout>
    );
}
