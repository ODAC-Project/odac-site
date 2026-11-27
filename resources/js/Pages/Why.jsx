// SPDX-License-Identifier: Apache-2.0
import { Link } from "@inertiajs/react";
import Icon from "../Components/Icon";
import Layout from "../Components/Layout";
import { Card, CardTitle, PageHeader } from "../Components/ui";
import { useT } from "../i18n";

/**
 * Why the project exists, in the reader's terms: what a player does with each
 * level, and why a fact about the device improves the processing upstream.
 */
export default function Why({ figures }) {
    const { t, number } = useT();
    const totals = figures?.totals ?? {};
    const levels = figures?.levels ?? {};
    const identity = figures?.identity ?? {};
    return (
        <Layout title={t("why.title")}>
            <PageHeader title={t("why.title")} intro={t("why.intro")} />

            <Card className="p-5 sm:p-6">
                <CardTitle>{t("why.problem_title")}</CardTitle>
                <p className="mt-2 text-sm leading-relaxed text-ink-300">
                    {t("why.problem")}
                </p>
                <ul className="mt-4 grid gap-3 md:grid-cols-3">
                    {[1, 2, 3].map((n) => (
                        <li
                            key={n}
                            className="rounded-lg bg-ink-800 p-4 text-sm"
                        >
                            <p className="font-semibold text-ink-100">
                                {t(`why.example_${n}_title`)}
                            </p>
                            <p className="mt-1 text-ink-300">
                                {t(`why.example_${n}`)}
                            </p>
                        </li>
                    ))}
                </ul>
            </Card>

            <h2 className="mb-3 mt-8 text-lg font-semibold text-ink-100">
                {t("why.levels_title")}
            </h2>
            <ol className="grid gap-3 md:grid-cols-3">
                {[
                    ["identity", identity.identified],
                    ["level2", levels.level2],
                    ["level3", levels.level3],
                ].map(([key, count]) => (
                    <li
                        key={key}
                        className="rounded-xl border border-ink-700/60 bg-ink-850 p-5"
                    >
                        <p className="text-sm font-semibold text-ink-100">
                            {t(`ladder.${key}`)}
                        </p>
                        <p className="mt-2 text-sm text-ink-300">
                            {t(`why.${key}_long`)}
                        </p>
                        <p className="mt-3 text-sm text-ink-200">
                            <span className="font-semibold">
                                {t(`ladder.${key}_gives`)}
                            </span>
                        </p>
                        {count != null && totals.devices > 0 && (
                            <p className="mt-3 text-xs text-ink-400">
                                {t("why.count", {
                                    n: number(count),
                                    total: number(totals.devices),
                                })}
                            </p>
                        )}
                    </li>
                ))}
            </ol>

            <Card className="mt-8 p-5 sm:p-6">
                <CardTitle>{t("why.rules_title")}</CardTitle>
                <ul className="mt-3 space-y-2 text-sm text-ink-300">
                    {[1, 2, 3].map((n) => (
                        <li key={n} className="flex gap-2">
                            <Icon
                                name="check"
                                className="mt-0.5 h-4 w-4 shrink-0 text-accent-400"
                            />
                            {t(`why.rule_${n}`)}
                        </li>
                    ))}
                </ul>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Link
                        href="/proposer?type=new_device"
                        className="rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-300"
                    >
                        {t("why.cta_new")}
                    </Link>
                    <Link
                        href="/devices"
                        className="rounded-lg border border-ink-600 px-4 py-2 text-sm text-ink-200 hover:border-accent-400/60 hover:text-accent-300"
                    >
                        {t("why.cta_browse")}
                    </Link>
                </div>
            </Card>
        </Layout>
    );
}
