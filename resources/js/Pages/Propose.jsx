// SPDX-License-Identifier: Apache-2.0
import { Link, useForm, usePage } from "@inertiajs/react";
import Icon from "../Components/Icon";
import Layout from "../Components/Layout";
import { Card, CardTitle, PageHeader } from "../Components/ui";
import { useT } from "../i18n";

const KINDS = ["new_device", "change", "comment"];

/** The same proposal as a pre-filled GitHub issue, for people without a Mozaik Labs account. */
function githubIssueUrl(
    repo,
    t,
    { kind, device, deviceName, field, message, source },
) {
    const subject = device ? device.odac_id : deviceName || "…";
    const title = `${t(`propose.kinds.${kind}`)} : ${subject}${field ? ` (${field})` : ""}`;
    const body = [
        device
            ? `- ${t("propose.gh_device")} : https://odac.tune.audio/${device.odac_id}`
            : `- ${t("propose.gh_device")} : ${deviceName || ""}`,
        field ? `- ${t("propose.gh_field")} : \`${field}\`` : null,
        `- ${t("propose.gh_source")} : ${source || ""}`,
        "",
        message || t("propose.gh_placeholder"),
        "",
        t("propose.gh_licence"),
    ]
        .filter((l) => l !== null)
        .join("\n");
    return `${repo}/issues/new?${new URLSearchParams({ title, body }).toString()}`;
}

export default function Propose({
    kind: initialKind,
    device,
    field,
    user,
    signin_enabled: signinEnabled,
    signin_failed: signinFailed,
    sent,
}) {
    const { app } = usePage().props;
    const { t, field: fieldLabel } = useT();
    const form = useForm({
        kind: initialKind,
        odac_id: device?.odac_id ?? "",
        field: field ?? "",
        device_name: "",
        message: "",
        source_url: "",
        pseudonym: user?.name ?? "",
        licence: false,
    });
    const d = form.data;
    const needsDevice = d.kind !== "new_device" && !device;
    const returnTo =
        typeof window !== "undefined"
            ? window.location.pathname + window.location.search
            : "/proposer";
    const github = githubIssueUrl(app.github_url, t, {
        kind: d.kind,
        device,
        deviceName: d.device_name,
        field: d.field,
        message: d.message,
        source: d.source_url,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post("/proposer", {
            preserveScroll: true,
            onSuccess: () =>
                form.reset("message", "source_url", "device_name", "licence"),
        });
    };

    return (
        <Layout title={t("propose.title")}>
            <PageHeader title={t("propose.title")} intro={t("propose.intro")} />

            {sent && (
                <p
                    className="mb-4 flex items-start gap-2 rounded-xl bg-accent-400/10 p-4 text-sm text-accent-200 ring-1 ring-inset ring-accent-400/30"
                    role="status"
                >
                    <Icon name="check" className="mt-0.5 h-4 w-4 shrink-0" />
                    {t("propose.sent")}
                </p>
            )}
            {signinFailed && (
                <p
                    className="mb-4 rounded-xl bg-rose-400/10 p-4 text-sm text-rose-200 ring-1 ring-inset ring-rose-400/30"
                    role="alert"
                >
                    {t("propose.signin_failed")}
                </p>
            )}

            <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                <Card className="p-5 sm:p-6">
                    <form onSubmit={submit} className="space-y-5">
                        <fieldset>
                            <legend className="text-sm font-semibold text-ink-100">
                                {t("propose.what")}
                            </legend>
                            <div className="mt-2 grid gap-2 sm:grid-cols-3">
                                {KINDS.map((k) => (
                                    <label
                                        key={k}
                                        className={`cursor-pointer rounded-lg border p-3 text-sm ${d.kind === k ? "border-accent-400 bg-accent-400/10 text-ink-100" : "border-ink-700 text-ink-300 hover:border-ink-500"}`}
                                    >
                                        <input
                                            type="radio"
                                            name="kind"
                                            value={k}
                                            checked={d.kind === k}
                                            onChange={() =>
                                                form.setData("kind", k)
                                            }
                                            className="sr-only"
                                        />
                                        <span className="block font-semibold">
                                            {t(`propose.kinds.${k}`)}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-ink-400">
                                            {t(`propose.kinds_hint.${k}`)}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        {d.kind === "new_device" ? (
                            <label className="block text-sm">
                                <span className="font-semibold text-ink-100">
                                    {t("propose.device_name")}
                                </span>
                                <input
                                    value={d.device_name}
                                    onChange={(e) =>
                                        form.setData(
                                            "device_name",
                                            e.target.value,
                                        )
                                    }
                                    placeholder={t("propose.device_name_ph")}
                                    className="mt-1 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 py-2 text-ink-100"
                                />
                                {form.errors.device_name && (
                                    <span className="mt-1 block text-xs text-rose-300">
                                        {t("propose.required")}
                                    </span>
                                )}
                            </label>
                        ) : device ? (
                            <p className="rounded-lg bg-ink-800 px-3 py-2 text-sm text-ink-200">
                                {t("propose.about")}{" "}
                                <Link
                                    href={`/${device.odac_id}`}
                                    className="font-semibold text-accent-300 underline"
                                >
                                    {device.brand} {device.model}
                                </Link>
                                {d.field && (
                                    <>
                                        {" · "}
                                        {fieldLabel(d.field) || d.field}{" "}
                                        <code className="text-xs text-ink-400">
                                            {d.field}
                                        </code>
                                        <button
                                            type="button"
                                            className="ml-2 text-xs text-ink-400 underline"
                                            onClick={() =>
                                                form.setData("field", "")
                                            }
                                        >
                                            {t("propose.any_field")}
                                        </button>
                                    </>
                                )}
                            </p>
                        ) : (
                            <p className="rounded-lg border border-dashed border-ink-600 px-3 py-2 text-sm text-ink-300">
                                {t("propose.pick_device")}{" "}
                                <Link
                                    href="/devices"
                                    className="text-accent-300 underline"
                                >
                                    {t("nav.devices")}
                                </Link>
                            </p>
                        )}

                        <label className="block text-sm">
                            <span className="font-semibold text-ink-100">
                                {t("propose.message")}
                            </span>
                            <span className="block text-xs text-ink-400">
                                {t(`propose.message_hint.${d.kind}`)}
                            </span>
                            <textarea
                                value={d.message}
                                onChange={(e) =>
                                    form.setData("message", e.target.value)
                                }
                                rows={6}
                                className="mt-1 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 py-2 text-ink-100"
                            />
                            {form.errors.message && (
                                <span className="mt-1 block text-xs text-rose-300">
                                    {t("propose.message_short")}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm">
                            <span className="font-semibold text-ink-100">
                                {t("propose.source")}
                            </span>
                            <span className="block text-xs text-ink-400">
                                {t("propose.source_hint")}
                            </span>
                            <input
                                type="url"
                                value={d.source_url}
                                onChange={(e) =>
                                    form.setData("source_url", e.target.value)
                                }
                                placeholder="https://"
                                className="mt-1 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 py-2 text-ink-100"
                            />
                            {form.errors.source_url && (
                                <span className="mt-1 block text-xs text-rose-300">
                                    {t("propose.bad_url")}
                                </span>
                            )}
                        </label>

                        {user ? (
                            <>
                                <label className="block text-sm">
                                    <span className="font-semibold text-ink-100">
                                        {t("propose.pseudonym")}
                                    </span>
                                    <span className="block text-xs text-ink-400">
                                        {t("propose.pseudonym_hint")}
                                    </span>
                                    <input
                                        value={d.pseudonym}
                                        onChange={(e) =>
                                            form.setData(
                                                "pseudonym",
                                                e.target.value,
                                            )
                                        }
                                        maxLength={64}
                                        className="mt-1 w-full rounded-lg border border-ink-600 bg-ink-900 px-3 py-2 text-ink-100"
                                    />
                                </label>
                                <label className="flex items-start gap-2 text-sm text-ink-300">
                                    <input
                                        type="checkbox"
                                        checked={d.licence}
                                        onChange={(e) =>
                                            form.setData(
                                                "licence",
                                                e.target.checked,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    <span>{t("propose.licence")}</span>
                                </label>
                                {form.errors.licence && (
                                    <span className="block text-xs text-rose-300">
                                        {t("propose.licence_needed")}
                                    </span>
                                )}
                                <div className="flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        disabled={
                                            form.processing || needsDevice
                                        }
                                        className="rounded-lg bg-accent-400 px-4 py-2 text-sm font-semibold text-ink-950 hover:bg-accent-300 disabled:opacity-50"
                                    >
                                        {t("propose.send")}
                                    </button>
                                    <span className="text-xs text-ink-400">
                                        {t("propose.signed_as", {
                                            name: user.name,
                                        })}
                                    </span>
                                </div>
                            </>
                        ) : (
                            <div className="rounded-lg bg-ink-800 p-4 text-sm">
                                <p className="text-ink-200">
                                    {t("propose.need_account")}
                                </p>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {signinEnabled && (
                                        <a
                                            href={`/connexion/mozaiklabs?return=${encodeURIComponent(returnTo)}`}
                                            className="inline-flex items-center gap-2 rounded-lg bg-accent-400 px-4 py-2 font-semibold text-ink-950 hover:bg-accent-300"
                                        >
                                            {t("propose.signin")}
                                        </a>
                                    )}
                                    <a
                                        href={github}
                                        rel="noopener"
                                        target="_blank"
                                        className="inline-flex items-center gap-2 rounded-lg border border-ink-600 px-4 py-2 text-ink-200 hover:border-accent-400/60 hover:text-accent-300"
                                    >
                                        <Icon
                                            name="github"
                                            className="h-4 w-4"
                                        />
                                        {t("propose.via_github")}
                                    </a>
                                </div>
                                {!signinEnabled && (
                                    <p className="mt-2 text-xs text-ink-400">
                                        {t("propose.signin_soon")}
                                    </p>
                                )}
                            </div>
                        )}
                    </form>
                </Card>

                <div className="space-y-4">
                    <Card className="p-5">
                        <CardTitle>{t("propose.after_title")}</CardTitle>
                        <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-ink-300">
                            {[1, 2, 3].map((n) => (
                                <li key={n}>{t(`propose.after_${n}`)}</li>
                            ))}
                        </ol>
                    </Card>
                    <Card className="p-5">
                        <CardTitle>{t("propose.good_title")}</CardTitle>
                        <ul className="mt-3 space-y-2 text-sm text-ink-300">
                            {[1, 2, 3].map((n) => (
                                <li key={n} className="flex gap-2">
                                    <Icon
                                        name="check"
                                        className="mt-0.5 h-4 w-4 shrink-0 text-accent-400"
                                    />
                                    {t(`propose.good_${n}`)}
                                </li>
                            ))}
                        </ul>
                    </Card>
                    {user && (
                        <Link
                            as="button"
                            method="post"
                            href="/deconnexion"
                            className="text-xs text-ink-400 underline"
                        >
                            {t("propose.signout")}
                        </Link>
                    )}
                </div>
            </div>
        </Layout>
    );
}
