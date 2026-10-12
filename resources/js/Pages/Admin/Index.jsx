import { Link, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { FcGoogle } from "react-icons/fc";
import {
    LuCheck,
    LuBriefcase,
    LuFlag,
    LuChevronDown,
    LuCopy,
    LuDownload,
    LuExternalLink,
    LuKeyRound,
    LuPackageCheck,
    LuPlus,
    LuEye,
    LuGlobe,
    LuSettings,
    LuStore,
    LuX,
} from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import WhatsAppButton from "@/Components/Dashboard/WhatsAppButton";
import {
    Avatar,
    CopyButton,
    EmptyState,
    primaryButton,
} from "@/Components/Dashboard/Ui";
import { formatNumber } from "@/lib/charts";
import { industryIcon } from "@/lib/industryIcons";

/** One-time reveal of a new owner's generated password. Dismissible, never re-shown. */
function CredentialsNotice({ password, email }) {
    const [copied, setCopied] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    if (dismissed) return null;

    async function copy() {
        try {
            await navigator.clipboard.writeText(
                email ? `Email: ${email}\nPassword: ${password}` : password,
            );
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard blocked (e.g. non-HTTPS) - the password is still select-all below.
        }
    }

    return (
        <div className="mb-6 overflow-hidden rounded-2xl border border-brand-accent/40 bg-brand-card shadow-sm">
            <div className="flex items-start gap-3 border-b border-brand-border bg-brand-accent/10 px-5 py-4">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-accent text-brand-accent-text">
                    <LuKeyRound className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-brand-text">
                        Shop created — owner login ready
                    </p>
                    <p className="mt-0.5 text-sm text-brand-muted">
                        This password is shown{" "}
                        <strong className="text-brand-text">once</strong>. Copy
                        it now and share it with the owner securely.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setDismissed(true)}
                    aria-label="Dismiss"
                    className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg"
                >
                    <LuX className="h-4 w-4" />
                </button>
            </div>

            <div className="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                {email && (
                    <div className="min-w-0">
                        <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">
                            Email
                        </p>
                        <p className="mt-1 truncate rounded-lg bg-brand-bg px-3 py-2 font-mono text-sm text-brand-text">
                            {email}
                        </p>
                    </div>
                )}
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">
                        Password
                    </p>
                    <p className="mt-1 select-all break-all rounded-lg bg-brand-bg px-3 py-2 font-mono text-sm text-brand-text">
                        {password}
                    </p>
                </div>
                <button type="button" onClick={copy} className={primaryButton}>
                    {copied ? (
                        <LuCheck className="h-4 w-4" />
                    ) : (
                        <LuCopy className="h-4 w-4" />
                    )}
                    {copied ? "Copied" : "Copy login"}
                </button>
            </div>
        </div>
    );
}

/* ---------- Status ---------- */

// Status reads by label + icon-dot, never colour alone.
const STATUS = {
    active: {
        label: "Active",
        dot: "bg-emerald-500",
        tone: "bg-emerald-500/10 text-emerald-700",
        order: 0,
    },
    quiet: {
        label: "Quiet",
        dot: "bg-amber-500",
        tone: "bg-amber-500/10 text-amber-700",
        order: 1,
    },
    not_started: {
        label: "Not started",
        dot: "bg-slate-400",
        tone: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border",
        order: 2,
    },
    // Blocked by the admin: card, menu, staff app and dashboard are off.
    blocked: {
        label: "Blocked",
        dot: "bg-red-600",
        tone: "bg-red-500/10 text-red-700",
        order: 3,
    },
};

function StatusBadge({ status, title }) {
    const s = STATUS[status];

    return (
        <span
            title={title || undefined}
            className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${s.tone}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
            {s.label}
        </span>
    );
}

/** Filter chips that double as a summary: each shows how many shops it matches. */
function StatusChips({ value, onChange, counts }) {
    const chips = [
        ["all", "All"],
        ["active", "Active"],
        ["quiet", "Quiet"],
        ["not_started", "Not started"],
        ["blocked", "Blocked"],
        ["no_owner", "No owner"],
        ["no_display", "Display not ordered"],
        ["no_qr", "QR not downloaded"],
        ["no_welcome", "Welcome not sent"],
    ];

    return (
        <div
            className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1 md:flex-wrap md:overflow-visible"
            role="group"
            aria-label="Filter by status"
        >
            {chips.map(([key, label]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onChange(key)}
                    aria-pressed={value === key}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                        value === key
                            ? "bg-brand-deep text-white"
                            : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                    }`}
                >
                    {STATUS[key] && (
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${STATUS[key].dot}`}
                        />
                    )}
                    {label}
                    <span
                        className={`tabular-nums ${value === key ? "text-white/70" : "text-brand-muted/80"}`}
                    >
                        {counts[key]}
                    </span>
                </button>
            ))}
        </div>
    );
}

/** UK and India first (where we sell / will sell the counter display), then A-Z. */
const COUNTRY_FIRST = ["GB", "IN"];

/** [{value, label, count}] for every distinct value of `pick(shop)` among the shops. */
function countBy(shops, pick, labelOf) {
    const byValue = new Map();
    for (const s of shops) {
        const value = pick(s) ?? "";
        const entry = byValue.get(value) ?? { value, label: labelOf(s), count: 0 };
        entry.count += 1;
        byValue.set(value, entry);
    }
    return [...byValue.values()];
}

function countryOptions(shops) {
    const rank = (code) => {
        const i = COUNTRY_FIRST.indexOf(code);
        return i === -1 ? COUNTRY_FIRST.length : i;
    };
    return countBy(shops, (s) => s.country_code, (s) => s.country ?? "No country").sort(
        (a, b) => rank(a.value) - rank(b.value) || a.label.localeCompare(b.label),
    );
}

/** Most common first; "Not set" (not answered yet) last. */
function industryOptions(shops) {
    return countBy(shops, (s) => s.industry, (s) => (s.industry ? s.industry_label : "Not set")).sort(
        (a, b) => (a.value === "") - (b.value === "") || b.count - a.count || a.label.localeCompare(b.label),
    );
}

/**
 * A pill-shaped dropdown that narrows the grid (country, industry), with how
 * many shops each choice has. Dark while something is chosen.
 */
function SelectFilter({ label, icon: Icon, allLabel, value, onChange, options, total }) {
    return (
        <label className="relative flex min-w-0 items-center md:shrink-0">
            <span className="sr-only">{label}</span>
            <Icon className={`pointer-events-none absolute left-3 h-4 w-4 ${value === "all" ? "text-brand-muted" : "text-white/70"}`} />
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`h-[42px] w-full min-w-0 appearance-none truncate rounded-xl md:w-auto md:max-w-56 pl-9 pr-8 text-sm font-medium ring-1 ring-inset transition-colors focus:outline-none focus:ring-2 focus:ring-brand-accent ${
                    value === "all"
                        ? "bg-brand-card text-brand-text ring-brand-border hover:bg-brand-bg"
                        : "bg-brand-deep text-white ring-brand-deep"
                }`}
            >
                <option value="all">
                    {allLabel} ({total})
                </option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label} ({o.count})
                    </option>
                ))}
            </select>
            <LuChevronDown
                className={`pointer-events-none absolute right-3 h-3.5 w-3.5 ${value === "all" ? "text-brand-muted" : "text-white/70"}`}
            />
        </label>
    );
}

/** QR download states, worst first (sort order) - the ones that need a call come up top. */
const QR_STATE = {
    failed: { order: 0, label: "Download failed", tone: "bg-red-500/10 text-red-700" },
    not_opened: { order: 1, label: "Not opened", tone: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border" },
    opened: { order: 2, label: "Opened, not downloaded", tone: "bg-amber-500/10 text-amber-800" },
    downloaded: { order: 3, label: "Downloaded", tone: "bg-emerald-500/10 text-emerald-700" },
};

/** One line for the tooltip and the CSV. */
function qrSummary(qr) {
    const parts = [
        qr.state === "downloaded" ? `Downloaded ${qr.downloaded_label} (${qr.downloads} in total)` : QR_STATE[qr.state].label,
        qr.opened_label && `QR page first opened ${qr.opened_label}`,
        qr.failures > 0 && `${qr.failures} failed`,
    ];
    return parts.filter(Boolean).join(" · ");
}

/* ---------- Health (ShopHealth) & pipeline ---------- */

/** Segment look: worst first, as the tiles are ordered. */
const SEGMENT = {
    never_started: { tone: "bg-neutral-500/10 text-neutral-700", bar: "bg-neutral-400", ring: "ring-neutral-400", hint: "Signed up, no stamps yet" },
    stalled: { tone: "bg-orange-500/10 text-orange-800", bar: "bg-orange-500", ring: "ring-orange-500", hint: "No stamps for 14+ days" },
    declining: { tone: "bg-red-500/10 text-red-700", bar: "bg-red-500", ring: "ring-red-500", hint: "Was busy, now dropping" },
    trickling: { tone: "bg-amber-500/10 text-amber-800", bar: "bg-amber-500", ring: "ring-amber-500", hint: "Stamping, few come back" },
    healthy: { tone: "bg-emerald-500/10 text-emerald-700", bar: "bg-emerald-500", ring: "ring-emerald-500", hint: "Ready to convert" },
};

const STAGE_TONE = {
    trial: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border",
    activated: "bg-sky-500/10 text-sky-700",
    offered: "bg-violet-500/10 text-violet-700",
    paid: "bg-emerald-500/10 text-emerald-700",
    lost: "bg-red-500/10 text-red-700",
};

/** "in 5 days" / "today" / "ended 3 days ago". */
function trialText(days) {
    if (days === null || days === undefined) return null;
    if (days === 0) return "ends today";
    if (days > 0) return `${days} ${days === 1 ? "day" : "days"} left`;
    return `ended ${-days} ${days === -1 ? "day" : "days"} ago`;
}

/**
 * Where the shops stand: one tile per health segment with its count (within
 * the other filters). A tile is also the filter - tap again to clear.
 */
function SegmentTiles({ segments, counts, value, onChange }) {
    return (
        <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5" role="group" aria-label="Filter by health">
            {Object.entries(segments).map(([key, label]) => {
                const look = SEGMENT[key];
                const active = value === key;
                return (
                    <button
                        key={key}
                        type="button"
                        onClick={() => onChange(active ? "all" : key)}
                        aria-pressed={active}
                        className={`rounded-2xl border bg-brand-card p-3 text-left transition-shadow ${active ? `border-transparent ring-2 ${look.ring}` : "border-brand-border hover:shadow-sm"}`}
                    >
                        <span className="flex items-center gap-2 text-sm font-semibold text-brand-text">
                            <span className={`h-2.5 w-2.5 rounded-full ${look.bar}`} /> {label}
                        </span>
                        <span className="mt-1 block font-heading text-2xl font-bold tabular-nums text-brand-text">{counts[key] ?? 0}</span>
                        <span className="block truncate text-xs text-brand-muted">{look.hint}</span>
                    </button>
                );
            })}
        </div>
    );
}

/** Has an owner who hasn't had the WhatsApp welcome yet. */
const needsWelcome = (s) => s.owner_email && !s.welcome_sent_at;

/** Prints their own QR and hasn't managed to download it yet. */
const needsQr = (s) => s.qr && s.qr.state !== "downloaded";

/* ---------- Columns ---------- */

const num = (value) => (
    <span className="tabular-nums text-brand-text">{formatNumber(value)}</span>
);

// `undefined` (not null) sorts last with TanStack's sortUndefined.
const orUndefined = (value) => (value === null ? undefined : value);

function buildColumns(origin) {
    return [
        {
            id: "shop",
            accessorFn: (s) => `${s.name} ${s.slug}`,
            header: "Shop",
            sortingFn: (a, b) => a.original.name.localeCompare(b.original.name),
            meta: { label: "Shop", csv: (s) => s.name },
            enableHiding: false,
            cell: ({ row: { original: s } }) => (
                <div className="flex min-w-48 max-w-64 items-center gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                        <LuStore className="h-4 w-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-brand-text">
                            {s.name}
                        </p>
                        <p className="truncate font-mono text-xs text-brand-muted">
                            /s/{s.slug}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            // ShopHealth: score 0-100 + segment; the reasons on hover.
            id: "health",
            accessorFn: (s) => s.health.score,
            header: "Health",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: {
                label: "Health",
                csv: (s) => `${s.health.score} ${s.health.segment} - ${s.health.reasons.join("; ")}`,
            },
            cell: ({ row: { original: s } }) => {
                const look = SEGMENT[s.health.segment];
                return (
                    <Link
                        href={`/admin/shops/${s.id}/settings#account`}
                        className="block w-32 rounded-lg hover:opacity-80"
                        title={[`${s.health.step_label} (step ${s.health.step} of 8)`, ...s.health.reasons].join("\n")}
                    >
                        <div className="flex items-center gap-2">
                            <span className="w-7 text-right text-sm font-semibold tabular-nums text-brand-text">{s.health.score}</span>
                            <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-brand-bg">
                                <span className={`block h-full rounded-full ${look.bar}`} style={{ width: `${s.health.score}%` }} />
                            </span>
                        </div>
                        <span className={`mt-1 inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium ${look.tone}`}>
                            {s.health.segment.replace("_", " ").replace(/^./, (c) => c.toUpperCase())}
                        </span>
                    </Link>
                );
            },
        },
        {
            id: "stage",
            accessorKey: "stage",
            header: "Stage",
            enableGlobalFilter: false,
            meta: { label: "Stage", csv: (s) => s.stage },
            cell: ({ row: { original: s } }) => (
                <span className={`whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STAGE_TONE[s.stage] ?? STAGE_TONE.trial}`}>
                    {s.stage === "offered" ? "Upgrade offered" : s.stage}
                </span>
            ),
        },
        {
            id: "trial",
            accessorFn: (s) => orUndefined(s.trial_ends_at ?? null),
            header: "Trial ends",
            enableGlobalFilter: false,
            sortUndefined: "last",
            meta: { label: "Trial ends", csv: (s) => s.trial_ends_label ?? "" },
            cell: ({ row: { original: s } }) =>
                s.trial_ends_label ? (
                    <div className="whitespace-nowrap">
                        <span className="text-sm text-brand-text">{s.trial_ends_label}</span>
                        <span
                            className={`block text-xs ${
                                s.trial_days_left < 0 ? "text-red-700" : s.trial_days_left <= 7 ? "font-medium text-amber-700" : "text-brand-muted"
                            }`}
                        >
                            {trialText(s.trial_days_left)}
                        </span>
                    </div>
                ) : (
                    <span className="text-xs text-brand-muted">-</span>
                ),
        },
        {
            id: "status",
            accessorKey: "status",
            header: "Status",
            sortingFn: (a, b) =>
                STATUS[a.original.status].order -
                STATUS[b.original.status].order,
            enableGlobalFilter: false,
            meta: { label: "Status", csv: (s) => STATUS[s.status].label },
            cell: ({ row: { original: s } }) => (
                <StatusBadge status={s.status} title={s.blocked_reason} />
            ),
        },
        {
            id: "industry",
            accessorFn: (s) => orUndefined(s.industry_label),
            header: "Business",
            sortUndefined: "last",
            meta: { label: "Type of business", csv: (s) => s.industry_label ?? "" },
            cell: ({ row: { original: s } }) => {
                if (!s.industry) {
                    return <span className="whitespace-nowrap text-xs text-brand-muted">Not set</span>;
                }
                const Icon = industryIcon(s.industry);
                return (
                    <span className="inline-flex max-w-44 items-center gap-1.5 text-sm text-brand-text" title={s.industry_label}>
                        <Icon className="h-4 w-4 shrink-0 text-brand-muted" />
                        <span className="truncate">{s.industry_label}</span>
                    </span>
                );
            },
        },
        {
            // Counter display ordered (paid online, or recorded by an admin).
            id: "display",
            accessorFn: (s) => orUndefined(s.product_ordered_at),
            header: "Display",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: {
                label: "Counter display",
                csv: (s) =>
                    s.product_ordered_label
                        ? `Ordered ${s.product_ordered_label}`
                        : s.product_awaiting
                          ? "Awaiting payment"
                          : "Not ordered",
            },
            cell: ({ row: { original: s } }) =>
                s.product_ordered_label ? (
                    <span className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        <LuPackageCheck className="h-3.5 w-3.5" />
                        {s.product_ordered_label}
                    </span>
                ) : s.product_awaiting ? (
                    <span className="whitespace-nowrap rounded-full bg-orange-500/10 px-2 py-0.5 text-xs font-medium text-orange-700">
                        Awaiting payment
                    </span>
                ) : (
                    <span className="whitespace-nowrap text-xs text-brand-muted">
                        Not ordered
                    </span>
                ),
        },
        {
            // Owners who print their own QR: found the page? did the download work?
            id: "qr",
            accessorFn: (s) => (s.qr ? QR_STATE[s.qr.state].order : undefined),
            header: "QR",
            enableGlobalFilter: false,
            sortUndefined: "last",
            meta: {
                label: "QR download",
                csv: (s) => (s.qr ? qrSummary(s.qr) : "Uses the display"),
            },
            cell: ({ row: { original: s } }) => {
                if (!s.qr) {
                    return <span className="whitespace-nowrap text-xs text-brand-muted">-</span>;
                }
                const state = QR_STATE[s.qr.state];
                return (
                    <span
                        title={qrSummary(s.qr)}
                        className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${state.tone}`}
                    >
                        {s.qr.state === "downloaded" && <LuDownload className="h-3.5 w-3.5" />}
                        {s.qr.state === "downloaded" ? `${s.qr.downloaded_label} · ${s.qr.downloads}×` : state.label}
                    </span>
                );
            },
        },
        {
            // The admin's first WhatsApp welcome to the owner (the WhatsApp button reports it).
            id: "welcome",
            accessorFn: (r) => orUndefined(r.welcome_sent_at ?? null),
            header: "Welcome",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: { label: "Welcome sent", csv: (r) => r.welcome_sent_label ?? "" },
            cell: ({ row: { original: r } }) =>
                r.welcome_sent_label ? (
                    <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        <LuCheck className="h-3.5 w-3.5" /> {r.welcome_sent_label}
                    </span>
                ) : (
                    <span className="whitespace-nowrap text-xs text-brand-muted">Not sent</span>
                ),
        },
        {
            id: "owner",
            accessorFn: (s) =>
                s.owner_name ? `${s.owner_name} ${s.owner_email}` : undefined,
            header: "Owner",
            sortingFn: (a, b) =>
                (a.original.owner_name ?? "").localeCompare(
                    b.original.owner_name ?? "",
                ),
            sortUndefined: "last",
            meta: {
                label: "Owner",
                csv: (s) =>
                    s.owner_name ? `${s.owner_name} <${s.owner_email}>` : "",
            },
            cell: ({ row: { original: s } }) =>
                s.owner_name ? (
                    <div className="flex min-w-44 max-w-60 items-center gap-2.5">
                        <Avatar name={s.owner_name} />
                        <div className="min-w-0">
                            <p className="flex items-center gap-1.5 truncate text-brand-text">
                                {s.owner_name}
                                {s.owner_via_google && (
                                    <FcGoogle
                                        className="h-3.5 w-3.5 shrink-0"
                                        title="Uses Google sign-in"
                                    />
                                )}
                            </p>
                            <p className="truncate text-xs text-brand-muted">
                                {s.owner_email}
                            </p>
                        </div>
                    </div>
                ) : (
                    <span className="inline-flex rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700">
                        No owner
                    </span>
                ),
        },
        {
            // Business contact from shop setup - may differ from the login owner.
            id: "contact",
            accessorFn: (s) =>
                s.contact_phone
                    ? `${s.contact_name ?? ""} ${s.contact_phone} ${s.contact_email ?? ""}`
                    : undefined,
            header: "Contact",
            enableSorting: false,
            meta: {
                label: "Contact",
                csv: (s) =>
                    [s.contact_name, s.contact_phone, s.contact_email]
                        .filter(Boolean)
                        .join(" · "),
            },
            cell: ({ row: { original: s } }) =>
                s.contact_phone ? (
                    <div className="min-w-40 max-w-56">
                        <p className="truncate text-brand-text">
                            {s.contact_name}
                        </p>
                        <a
                            href={`tel:${s.contact_phone_tel}`}
                            className="block truncate text-xs tabular-nums text-brand-muted hover:text-brand-accent"
                        >
                            {s.contact_phone}
                        </a>
                        {s.contact_email && (
                            <a
                                href={`mailto:${s.contact_email}`}
                                className="block truncate text-xs text-brand-muted hover:text-brand-accent"
                            >
                                {s.contact_email}
                            </a>
                        )}
                    </div>
                ) : (
                    <span className="text-xs text-brand-muted">Not given</span>
                ),
        },
        {
            id: "location",
            // Searchable by country too ("India", "United Kingdom").
            accessorFn: (s) => orUndefined(s.address && [s.address, s.country].filter(Boolean).join(", ")),
            header: "Location",
            // Country, then state, then town.
            sortingFn: (a, b) =>
                ["country", "state", "town"].reduce(
                    (order, key) => order || (a.original[key] ?? "").localeCompare(b.original[key] ?? ""),
                    0,
                ),
            sortUndefined: "last",
            meta: {
                label: "Location",
                csv: (s) =>
                    [
                        [s.address, s.country].filter(Boolean).join(", "),
                        s.delivery_address
                            ? `Deliver to: ${s.delivery_address}`
                            : null,
                    ]
                        .filter(Boolean)
                        .join(" | "),
            },
            cell: ({ row: { original: s } }) =>
                s.town ? (
                    <div
                        className="min-w-32 max-w-56"
                        title={[
                            [s.address, s.country].filter(Boolean).join(", "),
                            s.delivery_address &&
                                `Deliver to: ${s.delivery_address}`,
                        ]
                            .filter(Boolean)
                            .join("\n")}
                    >
                        <p className="truncate text-brand-text">{[s.town, s.state].filter(Boolean).join(", ")}</p>
                        <p className="truncate text-xs text-brand-muted">
                            {[s.country, s.postcode, s.delivery_address && "separate delivery address"].filter(Boolean).join(" · ")}
                        </p>
                    </div>
                ) : (
                    <span className="text-xs text-brand-muted">Not given</span>
                ),
        },
        {
            id: "customers",
            accessorKey: "customers",
            header: "Customers",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Customers", align: "right" },
            cell: ({ row: { original: s } }) => (
                <div>
                    {num(s.customers)}
                    {s.new_customers_30d > 0 && (
                        <p className="text-xs text-emerald-700">
                            +{formatNumber(s.new_customers_30d)} in 30d
                        </p>
                    )}
                </div>
            ),
        },
        {
            id: "stamps_30d",
            accessorKey: "stamps_30d",
            header: "Stamps (30d)",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Stamps (30 days)", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "rewards_total",
            accessorKey: "rewards_total",
            header: "Rewards",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Rewards redeemed", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "rating",
            accessorFn: (s) => orUndefined(s.rating),
            header: "Rating",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: {
                label: "Rating",
                align: "right",
                csv: (s) => s.rating ?? "",
            },
            cell: ({ row: { original: s } }) =>
                s.rating === null ? (
                    <span className="text-brand-muted">–</span>
                ) : (
                    <span className="whitespace-nowrap tabular-nums text-brand-text">
                        {s.rating} ★
                        <span className="ml-1 text-xs text-brand-muted">
                            ({formatNumber(s.reviews)})
                        </span>
                    </span>
                ),
        },
        {
            id: "staff",
            accessorKey: "staff",
            header: "Staff",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Staff", align: "right" },
            cell: ({ getValue }) => num(getValue()),
        },
        {
            id: "card",
            accessorFn: (s) => s.reward_title,
            header: "Loyalty card",
            enableSorting: false,
            meta: {
                label: "Loyalty card",
                csv: (s) => `${s.max_stamps} stamps: ${s.reward_title}`,
            },
            cell: ({ row: { original: s } }) => (
                <div className="max-w-56">
                    <p
                        className="truncate text-brand-text"
                        title={s.reward_title}
                    >
                        {s.reward_title}
                    </p>
                    <p className="text-xs text-brand-muted">
                        {s.max_stamps} stamps
                    </p>
                </div>
            ),
        },
        {
            id: "last_activity",
            accessorFn: (s) => orUndefined(s.last_activity_at),
            header: "Last scan",
            enableGlobalFilter: false,
            sortDescFirst: true,
            sortUndefined: "last",
            meta: { label: "Last scan", csv: (s) => s.last_activity_at ?? "" },
            cell: ({ row: { original: s } }) => (
                <span className="whitespace-nowrap text-brand-muted">
                    {s.last_activity ?? "Never"}
                </span>
            ),
        },
        {
            id: "created",
            accessorKey: "created_at",
            header: "Added",
            enableGlobalFilter: false,
            sortDescFirst: true,
            meta: { label: "Added", csv: (s) => s.created_label },
            cell: ({ row: { original: s } }) => (
                <span className="whitespace-nowrap text-brand-muted">
                    {s.created_label}
                </span>
            ),
        },
        {
            id: "actions",
            header: () => <span className="sr-only">Actions</span>,
            enableSorting: false,
            enableHiding: false,
            enableGlobalFilter: false,
            meta: { csv: false, stickyRight: true },
            cell: ({ row: { original: s } }) => (
                <div className="flex items-center justify-end gap-0.5">
                    <WhatsAppButton
                        number={s.contact_whatsapp}
                        name={s.contact_name ? `${s.contact_name} · ${s.name}` : s.name}
                        shopId={s.id}
                        welcomeSentLabel={s.welcome_sent_label}
                    />
                    <Link
                        href={`/admin/shops/${s.id}/settings`}
                        aria-label={`Configure ${s.name}`}
                        title="Configure shop"
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                    >
                        <LuSettings className="h-4 w-4" />
                    </Link>
                    {s.owner_email && (
                        <Link
                            href={`/admin/shops/${s.id}/view-as-owner`}
                            method="post"
                            as="button"
                            aria-label={`View ${s.name} as its owner`}
                            title="View as owner (read-only)"
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                        >
                            <LuEye className="h-4 w-4" />
                        </Link>
                    )}
                    <CopyButton
                        text={`${origin}/s/${s.slug}`}
                        label={`Copy ${s.name}'s card link`}
                    />
                    <a
                        href={`/s/${s.slug}?preview=1`}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`Open ${s.name}'s customer page`}
                        title="Open customer page"
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                    >
                        <LuExternalLink className="h-4 w-4" />
                    </a>
                </div>
            ),
        },
    ];
}

/* ---------- Page ---------- */

/** Not ordered the primary product, and not arranged with us either - the ones to call. */
const needsDisplay = (s) => !s.product_ordered_at && !s.product_awaiting;

export default function Index({ shops, quietDays, primaryProduct, segments = {}, stages = {} }) {
    const { flash } = usePage().props;
    const [status, setStatus] = useState("all");
    const [country, setCountry] = useState("all");
    const [industry, setIndustry] = useState("all");
    const [segment, setSegment] = useState("all");
    const [stage, setStage] = useState("all");

    // Country, business type and stage narrow everything; the health tiles count within them.
    const base = useMemo(
        () =>
            shops.filter(
                (s) =>
                    (country === "all" || (s.country_code ?? "") === country) &&
                    (industry === "all" || (s.industry ?? "") === industry) &&
                    (stage === "all" || s.stage === stage),
            ),
        [shops, country, industry, stage],
    );
    const segmentCounts = useMemo(
        () => base.reduce((acc, s) => ({ ...acc, [s.health.segment]: (acc[s.health.segment] ?? 0) + 1 }), {}),
        [base],
    );
    // The status chips count and filter within all of the above.
    const inCountry = useMemo(
        () => (segment === "all" ? base : base.filter((s) => s.health.segment === segment)),
        [base, segment],
    );
    const stageOptions = useMemo(
        () =>
            Object.entries(stages)
                .map(([value, label]) => ({ value, label, count: shops.filter((s) => s.stage === value).length }))
                .filter((o) => o.count > 0),
        [shops, stages],
    );
    // Each dropdown's counts follow the other's choice.
    const countries = useMemo(
        () => countryOptions(industry === "all" ? shops : shops.filter((s) => (s.industry ?? "") === industry)),
        [shops, industry],
    );
    const industries = useMemo(
        () => industryOptions(country === "all" ? shops : shops.filter((s) => (s.country_code ?? "") === country)),
        [shops, country],
    );

    const columns = useMemo(() => buildColumns(window.location.origin), []);

    const counts = useMemo(
        () => ({
            all: inCountry.length,
            active: inCountry.filter((s) => s.status === "active").length,
            quiet: inCountry.filter((s) => s.status === "quiet").length,
            not_started: inCountry.filter((s) => s.status === "not_started").length,
            blocked: inCountry.filter((s) => s.status === "blocked").length,
            no_owner: inCountry.filter((s) => !s.owner_email).length,
            no_display: inCountry.filter(needsDisplay).length,
            no_qr: inCountry.filter(needsQr).length,
            no_welcome: inCountry.filter(needsWelcome).length,
        }),
        [inCountry],
    );

    const rows = useMemo(() => {
        if (status === "all") return inCountry;
        if (status === "no_owner") return inCountry.filter((s) => !s.owner_email);
        if (status === "no_display") return inCountry.filter(needsDisplay);
        if (status === "no_qr") return inCountry.filter(needsQr);
        if (status === "no_welcome") return inCountry.filter(needsWelcome);

        return inCountry.filter((s) => s.status === status);
    }, [inCountry, status]);

    return (
        <AdminLayout
            title="Shops"
            description={
                status === "no_display" && primaryProduct
                    ? `Shops that haven't ordered the ${primaryProduct} (and have no order awaiting payment) - with their contact details to follow up.`
                    : status === "no_qr"
                      ? "Shops that print their own QR but haven't downloaded it yet. Not opened = may not have found it; opened or failed = may need help downloading."
                      : `Every shop on TaDa Tap and the owner who runs it. Quiet = no stamps for ${quietDays} days.`
            }
            actions={
                <Link href="/admin/shops/create" className={primaryButton}>
                    <LuPlus className="h-4 w-4" /> Add shop
                </Link>
            }
        >
            {flash?.generatedPassword && (
                <CredentialsNotice
                    password={flash.generatedPassword}
                    email={flash.createdOwnerEmail}
                />
            )}

            {shops.length === 0 ? (
                <div className="rounded-2xl border border-brand-border bg-brand-card shadow-sm">
                    <EmptyState icon={LuStore} title="No shops yet">
                        Add the first shop to create its owner login, or share
                        the sign-up page.
                        <span className="mt-4 block">
                            <Link
                                href="/admin/shops/create"
                                className={primaryButton}
                            >
                                <LuPlus className="h-4 w-4" /> Add shop
                            </Link>
                        </span>
                    </EmptyState>
                </div>
            ) : (
                <>
                <SegmentTiles segments={segments} counts={segmentCounts} value={segment} onChange={setSegment} />
                <DataTable
                    data={rows}
                    columns={columns}
                    initialSorting={[{ id: "created", desc: true }]}
                    initialHidden={{ card: false, staff: false }}
                    searchPlaceholder="Search shop, link or owner"
                    storageKey="admin.shops.table"
                    exportName="tada-tap-shops"
                    filters={
                        <>
                            <SelectFilter
                                label="Filter by country"
                                icon={LuGlobe}
                                allLabel="All countries"
                                value={country}
                                onChange={setCountry}
                                options={countries}
                                total={countries.reduce((n, o) => n + o.count, 0)}
                            />
                            <SelectFilter
                                label="Filter by type of business"
                                icon={LuBriefcase}
                                allLabel="All businesses"
                                value={industry}
                                onChange={setIndustry}
                                options={industries}
                                total={industries.reduce((n, o) => n + o.count, 0)}
                            />
                            <SelectFilter
                                label="Filter by pipeline stage"
                                icon={LuFlag}
                                allLabel="All stages"
                                value={stage}
                                onChange={setStage}
                                options={stageOptions}
                                total={shops.length}
                            />
                        </>
                    }
                    toolbar={
                        <StatusChips
                            value={status}
                            onChange={setStatus}
                            counts={counts}
                        />
                    }
                    filteredExternally={status !== "all" || country !== "all" || industry !== "all" || segment !== "all" || stage !== "all"}
                    onClearAll={() => {
                        setStatus("all");
                        setCountry("all");
                        setIndustry("all");
                        setSegment("all");
                        setStage("all");
                    }}
                    empty={<EmptyState icon={LuStore} title="No shops match" />}
                />
                </>
            )}
        </AdminLayout>
    );
}
