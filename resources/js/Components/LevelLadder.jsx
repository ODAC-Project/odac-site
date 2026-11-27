// SPDX-License-Identifier: Apache-2.0
import { Link } from "@inertiajs/react";
import Icon from "./Icon";
import { proposeHref } from "./SignalPath";
import { useT } from "../i18n";

/**
 * Where this record stands, as three steps a player climbs: recognise the
 * device, know what it accepts, know what it does inside. Each step says what
 * it lets the player do, and what is still missing for this device.
 */
export default function LevelLadder({ identity, completeness }) {
    const { t, field } = useT();
    const steps = [
        { key: "identity", done: identity.identified, missing: [] },
        {
            key: "level2",
            done: identity.level >= 2,
            missing: completeness?.level2_missing ?? [],
        },
        {
            key: "level3",
            done: identity.level >= 3,
            missing: completeness?.level3_missing ?? [],
        },
    ];
    return (
        <ol className="grid gap-3 md:grid-cols-3">
            {steps.map((s) => (
                <li
                    key={s.key}
                    className={`rounded-xl border p-4 ${s.done ? "border-accent-400/40 bg-accent-400/5" : "border-ink-700/60 bg-ink-850"}`}
                >
                    <p className="flex items-center gap-2 text-sm font-semibold text-ink-100">
                        <span
                            className={`flex h-5 w-5 items-center justify-center rounded-full ${s.done ? "bg-accent-400 text-ink-950" : "border border-ink-500 text-ink-500"}`}
                        >
                            {s.done && (
                                <Icon name="check" className="h-3.5 w-3.5" />
                            )}
                        </span>
                        {t(`ladder.${s.key}`)}
                    </p>
                    <p className="mt-2 text-sm text-ink-300">
                        {t(`ladder.${s.key}_gives`)}
                    </p>
                    {!s.done && s.missing.length > 0 && (
                        <div className="mt-3">
                            <p className="text-xs text-ink-400">
                                {t("ladder.missing")}
                            </p>
                            <ul className="mt-1 flex flex-wrap gap-1.5">
                                {s.missing.map((f) => (
                                    <li key={f}>
                                        <Link
                                            href={proposeHref(
                                                identity.odac_id,
                                                f,
                                            )}
                                            className="inline-block rounded-md bg-amber-400/10 px-2 py-0.5 text-xs text-amber-200 ring-1 ring-inset ring-amber-400/30 hover:bg-amber-400/20"
                                        >
                                            {field(f) || f}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {!s.done && s.key === "identity" && (
                        <p className="mt-3 text-xs text-ink-400">
                            {t("ladder.identity_how")}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
