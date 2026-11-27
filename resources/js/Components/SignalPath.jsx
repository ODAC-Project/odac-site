// SPDX-License-Identifier: Apache-2.0
import { Link } from "@inertiajs/react";
import { Badge } from "./ui";
import Value from "./Value";
import { useT } from "../i18n";

/**
 * The sheet read as the signal travels: what goes in, what the device accepts,
 * what converts it, what happens inside, what comes out. Each step says why a
 * player cares, and each blank says so - with a way to fill it - instead of
 * disappearing. Built only from the summary rows the server already sends.
 */
export const STAGES = [
    {
        key: "formats",
        fields: ["pcm_max_hz", "bits", "dsd_max", "native_dsd", "dop"],
    },
    {
        key: "conversion",
        fields: ["chip", "family", "technology", "modulator_order"],
    },
    {
        key: "inside",
        fields: [
            "dsd_direct",
            "dsd_reconverted_to_pcm",
            "pcm_reconverted_to_dsd",
            "internal_bypass",
        ],
    },
    {
        key: "output",
        fields: ["analog_filter", "pcm_dsd_level_offset_db", "device_settings"],
    },
];
// Shown even when absent: the blanks that matter. Others appear only if filled.
const ALWAYS = new Set([
    "pcm_max_hz",
    "dsd_max",
    "native_dsd",
    "dop",
    "chip",
    "family",
    "dsd_direct",
    "dsd_reconverted_to_pcm",
    "pcm_dsd_level_offset_db",
    "analog_filter",
]);
const INPUT_FIELDS = ["pcm_max_hz", "bits", "dsd_max", "dop_max"];

export function proposeHref(odacId, field, type = "change") {
    const q = new URLSearchParams({ type, device: odacId });
    if (field) q.set("field", field);
    return `/proposer?${q.toString()}`;
}

function Item({ field, row, odacId, familyNames, proofOf }) {
    const { t } = useT();
    const missing = !row;
    return (
        <li
            className={`rounded-lg px-3 py-2 ${missing ? "border border-dashed border-ink-600 bg-transparent" : "bg-ink-800"}`}
        >
            <div className="flex items-baseline justify-between gap-2">
                <span className="text-xs text-ink-400">
                    {t(`fields.${field}`)}
                </span>
                {row?.proof && (
                    <a
                        className="shrink-0 text-xs text-accent-300 underline decoration-accent-400/40"
                        href={`#preuve-${row.proof}`}
                    >
                        {t("sheet.proof_n", { n: row.proof })}
                    </a>
                )}
            </div>
            <div className="mt-0.5 break-words text-sm font-medium text-ink-100">
                {missing ? (
                    <Link
                        href={proposeHref(odacId, field)}
                        className="text-sm font-normal text-ink-400 hover:text-accent-300"
                    >
                        {t("path.unknown")} ·{" "}
                        <span className="underline">{t("path.i_know")}</span>
                    </Link>
                ) : row.contested ? (
                    <Badge tone="rose">{t("signals.contested")}</Badge>
                ) : (
                    <Value
                        field={field}
                        value={row.value}
                        familyNames={familyNames}
                    />
                )}
            </div>
        </li>
    );
}

function Stage({ n, title, why, children }) {
    return (
        <li className="relative flex gap-3 sm:gap-4">
            <div className="flex flex-col items-center">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-400/15 text-xs font-semibold text-accent-300">
                    {n}
                </span>
                <span
                    className="mt-1 w-px flex-1 bg-ink-700"
                    aria-hidden="true"
                />
            </div>
            <div className="min-w-0 flex-1 pb-5">
                <h3 className="font-semibold text-ink-100">{title}</h3>
                <p className="mt-0.5 text-sm text-ink-400">{why}</p>
                <div className="mt-3">{children}</div>
            </div>
        </li>
    );
}

export default function SignalPath({ rows, odacId, familyNames }) {
    const { t, has } = useT();
    // inputs.<interface>.<field> -> { interface: { field: row } }
    const inputs = {};
    for (const [key, row] of Object.entries(rows)) {
        const m = key.match(/^inputs\.([^.]+)\.(.+)$/);
        if (m) (inputs[m[1]] ??= {})[m[2]] = row;
    }
    const interfaces = Object.keys(inputs);

    return (
        <ol className="mt-2">
            <Stage n={1} title={t("path.inputs")} why={t("path.why_inputs")}>
                {interfaces.length === 0 ? (
                    <Link
                        href={proposeHref(odacId, "inputs")}
                        className="text-sm text-ink-400 hover:text-accent-300"
                    >
                        {t("path.no_inputs")} ·{" "}
                        <span className="underline">{t("path.i_know")}</span>
                    </Link>
                ) : (
                    <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {interfaces.map((i) => (
                            <li
                                key={i}
                                className="rounded-lg bg-ink-800 px-3 py-2"
                            >
                                <p className="text-sm font-semibold text-ink-100">
                                    {has(`interfaces.${i}`)
                                        ? t(`interfaces.${i}`)
                                        : i}
                                </p>
                                <dl className="mt-1 space-y-0.5 text-xs">
                                    {INPUT_FIELDS.filter(
                                        (f) => inputs[i][f],
                                    ).map((f) => (
                                        <div
                                            key={f}
                                            className="flex justify-between gap-2"
                                        >
                                            <dt className="text-ink-400">
                                                {t(`fields.${f}`)}
                                            </dt>
                                            <dd className="text-right text-ink-200">
                                                <Value
                                                    field={f}
                                                    value={inputs[i][f].value}
                                                />
                                            </dd>
                                        </div>
                                    ))}
                                    {!INPUT_FIELDS.some(
                                        (f) => inputs[i][f],
                                    ) && (
                                        <p className="text-ink-400">
                                            {t("path.input_no_ceiling")}
                                        </p>
                                    )}
                                </dl>
                            </li>
                        ))}
                    </ul>
                )}
            </Stage>
            {STAGES.map((s, idx) => {
                const shown = s.fields.filter((f) => rows[f] || ALWAYS.has(f));
                return (
                    <Stage
                        key={s.key}
                        n={idx + 2}
                        title={t(`path.${s.key}`)}
                        why={t(`path.why_${s.key}`)}
                    >
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {shown.map((f) => (
                                <Item
                                    key={f}
                                    field={f}
                                    row={rows[f]}
                                    odacId={odacId}
                                    familyNames={familyNames}
                                />
                            ))}
                        </ul>
                    </Stage>
                );
            })}
        </ol>
    );
}
