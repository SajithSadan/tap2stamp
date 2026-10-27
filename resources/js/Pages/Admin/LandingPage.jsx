import { Link, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useConfirm } from "@/Components/ConfirmDialog";
import {
    LuArrowDown,
    LuArrowLeft,
    LuArrowRight,
    LuArrowUp,
    LuCircleHelp,
    LuCreditCard,
    LuExternalLink,
    LuEye,
    LuGem,
    LuPanelTop,
    LuPlus,
    LuRotateCcw,
    LuSearch,
    LuShieldCheck,
    LuStar,
    LuTrash2,
    LuX,
} from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { PaymentsPanel, RecentPayments } from "@/Components/Dashboard/PlanBilling";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton, Switch } from "@/Components/Dashboard/Ui";

/**
 * Admin → Landing page: the public page at "/" - title, description, a
 * YouTube video and the pricing plans, each with a price per offered
 * currency. Visitors see their own country's currency when it's offered,
 * else the default (App\Support\LandingPage). One form, one save, split into
 * tabs like the shop settings page.
 *
 * The pricing plans are also the plans owners pay for (the `plans` table):
 * each says whether it's offered and which one new shops start on, with its
 * free trial. The Billing tab has the rest of plan billing.
 */

// Same link shapes as LandingPage::youtubeId().
const YOUTUBE = /^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/)|youtu\.be\/|youtube-nocookie\.com\/embed\/)([A-Za-z0-9_-]{11})/;

/** The page's tabs, and which form fields live on each (to flag errors; "plans" covers "plans.0.name"…). */
const TABS = [
    { key: "hero", label: "Hero", icon: LuPanelTop, fields: ["kicker", "title", "description", "primary_cta", "demo_cta", "youtube_url"] },
    { key: "trust", label: "Trust banner", icon: LuShieldCheck, fields: ["trust_title", "trust_description", "trust_points"] },
    { key: "pricing", label: "Pricing & plans", icon: LuGem, fields: ["pricing_title", "pricing_subtitle", "currencies", "default_currency", "plans"] },
    { key: "faq", label: "FAQ", icon: LuCircleHelp, fields: ["faq_title", "faqs"] },
    { key: "seo", label: "Search & sharing", icon: LuSearch, fields: ["seo_title", "seo_description"] },
    { key: "billing", label: "Billing", icon: LuCreditCard, fields: [] },
];

const covers = (field, key) => key === field || key.startsWith(`${field}.`);

function initialTab() {
    const hash = typeof window !== "undefined" ? window.location.hash.slice(1) : "";
    return TABS.some((t) => t.key === hash) ? hash : "hero";
}

const blankPlan = (currencies) => ({
    id: null,
    is_default: false,
    is_active: true,
    trial_days: 30,
    all_themes: false,
    name: "",
    description: "",
    note: "",
    badge: "",
    highlighted: false,
    cta_label: "Start Free",
    period: "/ year",
    billing_note: "",
    prices: Object.fromEntries(currencies.map((c) => [c, ""])),
    features_text: "",
});

/** Saved plans as form rows: features edited as one line each in a textarea. */
const toForm = (plans) =>
    plans.map(({ features, ...plan }) => ({
        ...plan,
        description: plan.description ?? "",
        note: plan.note ?? "",
        badge: plan.badge ?? "",
        period: plan.period ?? "",
        billing_note: plan.billing_note ?? "",
        features_text: (features ?? []).join("\n"),
        id: plan.id ?? null,
        is_default: plan.is_default ?? false,
        is_active: plan.is_active ?? true,
        trial_days: plan.trial_days ?? 30,
        all_themes: plan.all_themes ?? false,
    }));

/** Saved (or default) content as the form's values. */
const toFormData = (content) => ({
    seo_title: content.seo_title ?? "",
    seo_description: content.seo_description ?? "",
    faq_title: content.faq_title ?? "",
    faqs: (content.faqs ?? []).map((faq) => ({ question: faq.question ?? "", answer: faq.answer ?? "" })),
    kicker: content.kicker ?? "",
    title: content.title ?? "",
    description: content.description ?? "",
    primary_cta: content.primary_cta ?? "",
    demo_cta: content.demo_cta ?? "",
    youtube_url: content.youtube_url ?? "",
    trust_title: content.trust_title ?? "",
    trust_description: content.trust_description ?? "",
    trust_points_text: (content.trust_points ?? []).join("\n"),
    pricing_title: content.pricing_title ?? "",
    pricing_subtitle: content.pricing_subtitle ?? "",
    currencies: content.currencies ?? ["GBP"],
    default_currency: content.default_currency ?? "GBP",
    plans: toForm(content.plans ?? []),
});

/**
 * One setting: label (and an optional short hint) on the left, the control
 * on the right; stacked on phones. Rows sit in one panel, split by hairlines.
 */
function Row({ id, label, hint, error, children }) {
    return (
        <div className="grid gap-x-6 gap-y-1.5 px-5 py-4 sm:grid-cols-[13rem_minmax(0,1fr)]">
            <div>
                <label htmlFor={id} className="block text-sm font-medium text-brand-text">
                    {label}
                </label>
                {hint && <p className="mt-0.5 text-xs text-brand-muted">{hint}</p>}
            </div>
            <div className="min-w-0 max-w-2xl">
                {children}
                <FieldError message={error} />
            </div>
        </div>
    );
}

function Section({ title, description, action, children }) {
    return (
        <Panel title={title} description={description} action={action} bodyClassName="divide-y divide-brand-border">
            {children}
        </Panel>
    );
}

/** A small heading between groups of rows in one panel. */
function Group({ children }) {
    return <p className="bg-brand-bg/60 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">{children}</p>;
}

/** "54 / 60": a length hint that turns amber past what search results show. */
function Count({ value, ideal }) {
    const length = value.trim().length;
    return (
        <span className={length > ideal ? "text-amber-700" : ""}>
            {length} / {ideal} characters shown in search results
        </span>
    );
}

/** Roughly how the page shows up on Google - title, address, description. */
function SearchPreview({ title, description }) {
    return (
        <div className="rounded-xl border border-brand-border bg-white p-4">
            <p className="text-xs text-neutral-600">{window.location.host}</p>
            <p className="mt-0.5 truncate text-lg text-[#1a0dab]">{title || "Page title"}</p>
            <p className="mt-0.5 line-clamp-2 text-sm text-neutral-700">{description || "Description"}</p>
        </div>
    );
}

/** One currency picked + "Preview": the live page as a visitor paying in it sees it. */
function PreviewButton({ currencies }) {
    const [currency, setCurrency] = useState(currencies[0]);
    const shown = currencies.includes(currency) ? currency : currencies[0];

    return (
        <div className="flex items-stretch overflow-hidden rounded-xl border border-brand-border bg-brand-card">
            <select value={shown} onChange={(e) => setCurrency(e.target.value)} aria-label="Preview currency" className="bg-transparent px-2.5 text-sm text-brand-text outline-none">
                {currencies.map((code) => (
                    <option key={code} value={code}>
                        {code}
                    </option>
                ))}
            </select>
            <a
                href={`/?preview=1&currency=${shown}`}
                target="_blank"
                rel="noreferrer"
                className="flex items-center gap-1.5 border-l border-brand-border px-3 py-2 text-sm font-medium text-brand-text hover:bg-brand-bg"
            >
                <LuExternalLink className="h-4 w-4" /> Preview
            </a>
        </div>
    );
}

export default function LandingPage({ content, defaults, currencyOptions, limits, ipLookup, planShops = {}, payments, recentPayments = [] }) {
    const form = useForm(toFormData(content));
    const [confirm, confirmDialog] = useConfirm();
    const [tab, setTab] = useState(initialTab);
    // The plan being edited (one at a time).
    const [planIndex, setPlanIndex] = useState(0);
    const { data, errors } = form;

    const errorKeys = Object.keys(errors);
    const tabHasError = (t) => errorKeys.some((key) => t.fields.some((field) => covers(field, key)));
    const planHasError = (i) => errorKeys.some((key) => covers(`plans.${i}`, key));

    function openTab(key) {
        setTab(key);
        window.history.replaceState(window.history.state, "", `#${key}`);
    }

    // After a failed save, show the first tab (and plan) with a problem on it.
    useEffect(() => {
        const current = TABS.find((t) => t.key === tab);
        if (!tabHasError(current)) {
            const withError = TABS.find(tabHasError);
            if (withError) openTab(withError.key);
        }
        const plan = data.plans.findIndex((_, i) => planHasError(i));
        if (plan >= 0 && !planHasError(planIndex)) setPlanIndex(plan);
    }, [errors]);

    // The suggested copy and plans, into the form - nothing changes until Save.
    async function loadDefaults() {
        const ok = await confirm({
            title: "Load the default content?",
            message: "The whole form is replaced with the suggested search title and description, hero, trust banner, pricing plans and FAQ. Plans keep their place: the shops on each, the default and the free trial stay. Nothing is saved until you press Save - Discard brings back what's live.",
            confirmLabel: "Load defaults",
        });
        if (!ok) return;
        const suggested = toFormData(defaults);
        // The suggested plans take over the saved ones in order (same id, so their shops stay on them).
        const billing = ({ id, is_default, is_active, trial_days, all_themes }) => ({ id, is_default, is_active, trial_days, all_themes });
        const plans = suggested.plans.map((plan, i) => ({ ...plan, ...billing(form.data.plans[i] ?? blankPlan([])) }));
        // A default left out of the suggested ones comes back on the first.
        if (!plans.some((p) => p.is_default)) plans[0] = { ...plans[0], is_default: true, is_active: true };
        form.setData({ ...suggested, plans, youtube_url: form.data.youtube_url });
        setPlanIndex(0);
    }

    const symbol = (code) => currencyOptions.find((c) => c.code === code)?.symbol ?? `${code} `;
    const videoId = data.youtube_url.match(YOUTUBE)?.[1];

    const setPlan = (i, patch) => form.setData("plans", data.plans.map((p, j) => (j === i ? { ...p, ...patch } : p)));
    // Exactly one default: switching one on switches the others off.
    const makeDefault = (i) => form.setData("plans", data.plans.map((p, j) => ({ ...p, is_default: j === i, is_active: j === i ? true : p.is_active })));
    const movePlan = (i, by) => {
        const plans = [...data.plans];
        [plans[i], plans[i + by]] = [plans[i + by], plans[i]];
        form.setData("plans", plans);
        setPlanIndex(i + by);
    };
    function addPlan() {
        form.setData("plans", [...data.plans, blankPlan(data.currencies)]);
        setPlanIndex(data.plans.length);
    }

    async function removePlan(i) {
        const shops = planShops[data.plans[i].id] ?? 0;
        if (
            shops > 0 &&
            !(await confirm({
                title: `Remove ${data.plans[i].name || "this plan"}?`,
                message: `${shops} ${shops === 1 ? "shop is" : "shops are"} on it. On Save ${shops === 1 ? "it moves" : "they move"} to the default plan. To stop offering it but keep its shops on it, switch "Offered" off instead.`,
                confirmLabel: "Remove plan",
                danger: true,
            }))
        ) {
            return;
        }
        form.setData("plans", data.plans.filter((_, j) => j !== i));
        setPlanIndex(Math.max(0, i - 1));
    }

    const setFaq = (i, patch) => form.setData("faqs", data.faqs.map((f, j) => (j === i ? { ...f, ...patch } : f)));
    const moveFaq = (i, by) => {
        const faqs = [...data.faqs];
        [faqs[i], faqs[i + by]] = [faqs[i + by], faqs[i]];
        form.setData("faqs", faqs);
    };

    function addCurrency(code) {
        if (!code || data.currencies.includes(code)) return;
        form.setData((d) => ({
            ...d,
            currencies: [...d.currencies, code],
            plans: d.plans.map((p) => ({ ...p, prices: { ...p.prices, [code]: "" } })),
        }));
    }

    function removeCurrency(code) {
        form.setData((d) => {
            const currencies = d.currencies.filter((c) => c !== code);
            return {
                ...d,
                currencies,
                default_currency: d.default_currency === code ? currencies[0] : d.default_currency,
                plans: d.plans.map(({ prices, ...p }) => ({ ...p, prices: Object.fromEntries(Object.entries(prices).filter(([c]) => c !== code)) })),
            };
        });
    }

    function submit(e) {
        e.preventDefault();
        form.transform(({ trust_points_text, ...d }) => ({
            ...d,
            trust_points: trust_points_text.split("\n"),
            plans: d.plans.map(({ features_text, ...p }) => ({ ...p, features: features_text.split("\n") })),
        }));
        form.put("/admin/landing-page", { preserveScroll: true, onSuccess: () => form.setDefaults() });
    }

    const text = (key) => ({ id: key, value: data[key], onChange: (e) => form.setData(key, e.target.value), className: inputClass });
    const i = Math.min(planIndex, data.plans.length - 1);
    const plan = data.plans[i];
    const planText = (key) => ({ id: `plan-${key}`, value: plan[key], onChange: (e) => setPlan(i, { [key]: e.target.value }), className: inputClass });
    const shopsOn = (p) => (p.id ? planShops[p.id] ?? 0 : null);

    return (
        <AdminLayout
            title="Landing page & plans"
            description="The public page at your app's home, and the plans owners pay for."
            actions={
                <div className="flex flex-wrap gap-2">
                    <PreviewButton currencies={data.currencies} />
                    <Link href="/admin/plans/preview" className={secondaryButton}>
                        <LuEye className="h-4 w-4" /> Owner view
                    </Link>
                    <button type="button" onClick={loadDefaults} className={secondaryButton}>
                        <LuRotateCcw className="h-4 w-4" /> Load defaults
                    </button>
                </div>
            }
        >
            <nav role="tablist" aria-label="Landing page" className="-mx-1 mb-4 flex gap-1 overflow-x-auto border-b border-brand-border px-1 [scrollbar-width:none]">
                {TABS.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        role="tab"
                        aria-selected={tab === t.key}
                        onClick={() => openTab(t.key)}
                        className={`-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                            tab === t.key ? "border-brand-accent text-brand-text" : "border-transparent text-brand-muted hover:text-brand-text"
                        }`}
                    >
                        <t.icon className="h-4 w-4" />
                        {t.label}
                        {t.key === "pricing" && <span className="text-xs tabular-nums text-brand-muted">{data.plans.length}</span>}
                        {tabHasError(t) && <span className="h-1.5 w-1.5 rounded-full bg-red-600" aria-label="has errors" />}
                    </button>
                ))}
            </nav>

            {tab === "billing" && (
                <div className="space-y-4">
                    <PaymentsPanel payments={payments} />
                    <RecentPayments payments={recentPayments} />
                </div>
            )}

            {/* One form across the content tabs, one save. */}
            <form onSubmit={submit} noValidate className="space-y-4">
                {tab === "hero" && (
                    <Section title="Hero" description="The top of the page.">
                        <Row id="kicker" label="Badge" hint="Above the heading. Optional." error={errors.kicker}>
                            <input type="text" maxLength={120} placeholder="Digital Loyalty & Growth Engine for Retail, Dining & Salons" {...text("kicker")} />
                        </Row>
                        <Row id="title" label="Heading" error={errors.title}>
                            <input type="text" maxLength={120} {...text("title")} />
                        </Row>
                        <Row id="description" label="Subtitle" error={errors.description}>
                            <textarea rows={3} maxLength={400} {...text("description")} className={`${inputClass} resize-y`} />
                        </Row>
                        <Row id="primary_cta" label="Main button" hint="Opens sign-up." error={errors.primary_cta}>
                            <input type="text" maxLength={40} placeholder="Start 1-Month Free Trial" {...text("primary_cta")} />
                        </Row>
                        <Row id="demo_cta" label="Demo button" hint="Plays the video. Hidden without one." error={errors.demo_cta}>
                            <input type="text" maxLength={40} placeholder="Watch 60-Sec Demo" {...text("demo_cta")} />
                        </Row>
                        <Row id="youtube_url" label="YouTube video" hint="The video's link. Empty = no video." error={errors.youtube_url}>
                            <input type="url" placeholder="https://www.youtube.com/watch?v=…" {...text("youtube_url")} />
                            {videoId && <img src={`https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`} alt="Video thumbnail" className="mt-3 aspect-video w-60 rounded-xl object-cover" />}
                        </Row>
                    </Section>
                )}

                {tab === "trust" && (
                    <Section title="Trust banner" description="The dark strip under the video. Leave it all empty to hide it.">
                        <Row id="trust_title" label="Title" error={errors.trust_title}>
                            <input type="text" maxLength={120} {...text("trust_title")} />
                        </Row>
                        <Row id="trust_description" label="Text" error={errors.trust_description}>
                            <textarea rows={3} maxLength={400} {...text("trust_description")} className={`${inputClass} resize-y`} />
                        </Row>
                        <Row id="trust_points_text" label="Badges" hint={`One per line, up to ${limits.trustPoints}. Each gets a tick.`} error={errors.trust_points}>
                            <textarea rows={4} {...text("trust_points_text")} className={`${inputClass} resize-y`} />
                        </Row>
                    </Section>
                )}

                {tab === "pricing" && (
                    <>
                        <Section title="Pricing section">
                            <Row id="pricing_title" label="Title" error={errors.pricing_title}>
                                <input type="text" maxLength={120} placeholder="Simple, Transparent Annual Pricing" {...text("pricing_title")} />
                            </Row>
                            <Row id="pricing_subtitle" label="Subtitle" hint="Optional." error={errors.pricing_subtitle}>
                                <input type="text" maxLength={300} {...text("pricing_subtitle")} />
                            </Row>
                            <Row label="Currencies" hint="Visitors see their own country's, else the default." error={errors.currencies}>
                                {!ipLookup && (
                                    <p className="mb-3 rounded-xl bg-amber-500/10 px-3 py-2 text-xs text-amber-800">
                                        Add <code className="font-mono">IPINFO_TOKEN</code> to .env to detect visitors' countries. Until then everyone sees the default.
                                    </p>
                                )}
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {data.currencies.map((code) => (
                                        <span key={code} className="inline-flex items-center gap-1 rounded-full border border-brand-border bg-brand-bg py-0.5 pl-2.5 pr-0.5 text-sm">
                                            <span className="font-semibold text-brand-text">{code}</span>
                                            <span className="text-brand-muted">{symbol(code).trim()}</span>
                                            <button
                                                type="button"
                                                onClick={() => removeCurrency(code)}
                                                disabled={data.currencies.length === 1}
                                                aria-label={`Remove ${code}`}
                                                className="rounded-full p-1 text-brand-muted hover:bg-brand-card hover:text-red-600 disabled:invisible"
                                            >
                                                <LuX className="h-3.5 w-3.5" />
                                            </button>
                                        </span>
                                    ))}
                                    {data.currencies.length < currencyOptions.length && (
                                        <select value="" onChange={(e) => addCurrency(e.target.value)} aria-label="Add a currency" className={`${inputClass} !w-auto !py-1.5`}>
                                            <option value="">+ Add</option>
                                            {currencyOptions
                                                .filter((c) => !data.currencies.includes(c.code))
                                                .map((c) => (
                                                    <option key={c.code} value={c.code}>
                                                        {c.code} · {c.name}
                                                    </option>
                                                ))}
                                        </select>
                                    )}
                                </div>
                            </Row>
                            <Row id="default_currency" label="Default currency" hint="For countries whose currency isn't offered." error={errors.default_currency}>
                                <select {...text("default_currency")} className={`${inputClass} !w-40`}>
                                    {data.currencies.map((code) => (
                                        <option key={code} value={code}>
                                            {code}
                                        </option>
                                    ))}
                                </select>
                            </Row>
                        </Section>

                        <Panel
                            title="Plans"
                            description="The pricing cards, and the plans owners pay for on Billing & plan."
                            action={
                                data.plans.length < limits.plans && (
                                    <button type="button" onClick={addPlan} className={secondaryButton}>
                                        <LuPlus className="h-4 w-4" /> Add plan
                                    </button>
                                )
                            }
                            bodyClassName=""
                        >
                            {/* Pick the plan to edit; the order is the order on the page. */}
                            <div className="flex flex-wrap items-center gap-2 border-b border-brand-border px-5 py-3">
                                <div role="tablist" aria-label="Plans" className="flex flex-wrap gap-1.5">
                                    {data.plans.map((p, j) => (
                                        <button
                                            key={j}
                                            type="button"
                                            role="tab"
                                            aria-selected={j === i}
                                            onClick={() => setPlanIndex(j)}
                                            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                                                j === i ? "bg-brand-deep text-white" : "bg-brand-bg text-brand-text ring-1 ring-inset ring-brand-border hover:ring-brand-accent"
                                            } ${p.is_active ? "" : "opacity-60"}`}
                                        >
                                            {p.is_default && <LuStar className="h-3.5 w-3.5 text-brand-accent" aria-label="Default" />}
                                            {p.name || `Plan ${j + 1}`}
                                            {planHasError(j) && <span className="h-1.5 w-1.5 rounded-full bg-red-600" aria-label="has errors" />}
                                        </button>
                                    ))}
                                </div>
                                {plan && (
                                    <div className="ml-auto flex items-center gap-1">
                                        <button type="button" disabled={i === 0} onClick={() => movePlan(i, -1)} aria-label="Move earlier" className={`${secondaryButton} !px-2`}>
                                            <LuArrowLeft className="h-4 w-4" />
                                        </button>
                                        <button type="button" disabled={i === data.plans.length - 1} onClick={() => movePlan(i, 1)} aria-label="Move later" className={`${secondaryButton} !px-2`}>
                                            <LuArrowRight className="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            disabled={data.plans.length === 1 || plan.is_default}
                                            title={plan.is_default ? "Make another plan the default first" : undefined}
                                            onClick={() => removePlan(i)}
                                            aria-label="Remove plan"
                                            className={`${secondaryButton} !px-2 hover:!text-red-600`}
                                        >
                                            <LuTrash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                )}
                            </div>
                            {errors.plans && (
                                <div className="px-5 pt-3">
                                    <FieldError message={errors.plans} />
                                </div>
                            )}

                            {plan && (
                                <div key={i} className="divide-y divide-brand-border">
                                    <Group>Card</Group>
                                    <Row id="plan-name" label="Name" error={errors[`plans.${i}.name`]}>
                                        <input type="text" maxLength={40} placeholder="Starter" {...planText("name")} />
                                    </Row>
                                    <Row id="plan-badge" label="Badge" hint="A short pill, e.g. Most Popular." error={errors[`plans.${i}.badge`]}>
                                        <input type="text" maxLength={40} {...planText("badge")} />
                                    </Row>
                                    <Row id="plan-description" label="Who it's for" hint="Under the name." error={errors[`plans.${i}.description`]}>
                                        <input type="text" maxLength={200} placeholder="Best for Cafes, Restaurants & Busy Spas" {...planText("description")} />
                                    </Row>
                                    <Row id="plan-note" label="Offer line" hint="With a gift icon, above the price." error={errors[`plans.${i}.note`]}>
                                        <input type="text" maxLength={120} placeholder="Free for Year 1 when you buy hardware" {...planText("note")} />
                                    </Row>
                                    <Row
                                        id="plan-features_text"
                                        label="Features"
                                        hint={`One per line, up to ${limits.features}. "Title (detail)" shows the detail smaller; a line ending in ":" is a heading.`}
                                        error={errors[`plans.${i}.features`] ?? Object.entries(errors).find(([key]) => key.startsWith(`plans.${i}.features.`))?.[1]}
                                    >
                                        <textarea rows={7} {...planText("features_text")} className={`${inputClass} resize-y`} />
                                    </Row>
                                    <Row id="plan-cta_label" label="Button text" error={errors[`plans.${i}.cta_label`]}>
                                        <input type="text" maxLength={30} {...planText("cta_label")} />
                                    </Row>
                                    <Row label="Highlight" hint="Dark card, like the most popular one.">
                                        <Switch checked={plan.highlighted} onChange={(on) => setPlan(i, { highlighted: on })} />
                                    </Row>

                                    <Group>Price</Group>
                                    <Row label="Yearly price" hint="In every offered currency.">
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                            {data.currencies.map((code) => (
                                                <div key={code} className="min-w-0">
                                                    <div className="flex items-stretch overflow-hidden rounded-xl border border-brand-border bg-brand-card focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10">
                                                        <span className="flex w-12 shrink-0 items-center justify-center border-r border-brand-border bg-brand-bg text-xs font-semibold text-brand-muted">{code}</span>
                                                        <input
                                                            type="text"
                                                            inputMode="decimal"
                                                            aria-label={`${code} price`}
                                                            value={plan.prices[code] ?? ""}
                                                            onChange={(e) => setPlan(i, { prices: { ...plan.prices, [code]: e.target.value } })}
                                                            placeholder="0"
                                                            className="w-full min-w-0 bg-transparent px-2.5 py-2 text-sm tabular-nums text-brand-text outline-none"
                                                        />
                                                    </div>
                                                    <FieldError message={errors[`plans.${i}.prices.${code}`]} />
                                                </div>
                                            ))}
                                        </div>
                                    </Row>
                                    <Row id="plan-period" label="After the price" hint='e.g. "/ year"' error={errors[`plans.${i}.period`]}>
                                        <input type="text" maxLength={30} {...planText("period")} className={`${inputClass} !w-48`} />
                                    </Row>
                                    <Row id="plan-billing_note" label="Under the price" hint='e.g. "Billed annually". Optional.' error={errors[`plans.${i}.billing_note`]}>
                                        <input type="text" maxLength={60} {...planText("billing_note")} />
                                    </Row>

                                    <Group>Billing</Group>
                                    <Row
                                        label="Offered"
                                        hint="On this page and the owners' Billing & plan page. Off: hidden, shops on it keep it."
                                        error={errors[`plans.${i}.is_active`]}
                                    >
                                        <Switch checked={plan.is_active} disabled={plan.is_default} onChange={(on) => setPlan(i, { is_active: on })} />
                                    </Row>
                                    <Row label="Default plan" hint="New shops start on it. Only one.">
                                        <div className="flex items-center gap-3">
                                            <Switch checked={plan.is_default} disabled={plan.is_default} onChange={() => makeDefault(i)} />
                                            {shopsOn(plan) !== null && (
                                                <span className="text-sm text-brand-muted">
                                                    {shopsOn(plan)} {shopsOn(plan) === 1 ? "shop" : "shops"} on this plan
                                                </span>
                                            )}
                                        </div>
                                    </Row>
                                    <Row label="All themes" hint="Shops on this plan get every card theme and stamp icon, and a banner photo. Others get the free ones (Settings → Appearance).">
                                        <Switch checked={plan.all_themes} onChange={(on) => setPlan(i, { all_themes: on })} />
                                    </Row>
                                    {plan.is_default && (
                                        <Row id="plan-trial_days" label="Free trial" hint="Days, for new shops. 0 = none." error={errors[`plans.${i}.trial_days`]}>
                                            <input type="number" min={0} max={365} {...planText("trial_days")} className={`${inputClass} !w-32`} />
                                        </Row>
                                    )}
                                </div>
                            )}
                        </Panel>
                    </>
                )}

                {tab === "faq" && (
                    <Section title="FAQ" description="At the bottom of the page. Google reads them too. Clear a row to remove it.">
                        <Row id="faq_title" label="Heading" error={errors.faq_title}>
                            <input type="text" maxLength={120} placeholder="Frequently asked questions" {...text("faq_title")} />
                        </Row>
                        {data.faqs.map((faq, j) => (
                            <div key={j} className="grid gap-x-6 gap-y-1.5 px-5 py-4 sm:grid-cols-[13rem_minmax(0,1fr)]">
                                <div className="flex items-start justify-between gap-2 sm:block">
                                    <p className="text-sm font-medium text-brand-text">Question {j + 1}</p>
                                    <div className="flex gap-1 sm:mt-2">
                                        <button type="button" onClick={() => moveFaq(j, -1)} disabled={j === 0} aria-label="Move up" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text disabled:opacity-30">
                                            <LuArrowUp className="h-4 w-4" />
                                        </button>
                                        <button type="button" onClick={() => moveFaq(j, 1)} disabled={j === data.faqs.length - 1} aria-label="Move down" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text disabled:opacity-30">
                                            <LuArrowDown className="h-4 w-4" />
                                        </button>
                                        <button type="button" onClick={() => form.setData("faqs", data.faqs.filter((_, k) => k !== j))} aria-label="Remove question" className="rounded-lg p-1.5 text-brand-muted hover:bg-red-500/10 hover:text-red-700">
                                            <LuTrash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div className="min-w-0 max-w-2xl space-y-2">
                                    <input type="text" maxLength={200} placeholder="Question" aria-label={`Question ${j + 1}`} value={faq.question} onChange={(e) => setFaq(j, { question: e.target.value })} className={`${inputClass} font-medium`} />
                                    <FieldError message={errors[`faqs.${j}.question`]} />
                                    <textarea rows={3} maxLength={1000} placeholder="Answer" aria-label={`Answer ${j + 1}`} value={faq.answer} onChange={(e) => setFaq(j, { answer: e.target.value })} className={`${inputClass} resize-y`} />
                                    <FieldError message={errors[`faqs.${j}.answer`]} />
                                </div>
                            </div>
                        ))}
                        {data.faqs.length < limits.faqs && (
                            <div className="px-5 py-3">
                                <button type="button" onClick={() => form.setData("faqs", [...data.faqs, { question: "", answer: "" }])} className={secondaryButton}>
                                    <LuPlus className="h-4 w-4" /> Add question
                                </button>
                                <FieldError message={errors.faqs} />
                            </div>
                        )}
                    </Section>
                )}

                {tab === "seo" && (
                    <Section title="Search & sharing" description="On Google and when the link is shared. For every country, so no prices.">
                        <Row id="seo_title" label="Page title" hint={<Count value={data.seo_title} ideal={60} />} error={errors.seo_title}>
                            <input type="text" maxLength={70} placeholder={defaults.seo_title} {...text("seo_title")} />
                        </Row>
                        <Row id="seo_description" label="Description" hint={<Count value={data.seo_description} ideal={160} />} error={errors.seo_description}>
                            <textarea rows={3} maxLength={200} placeholder={defaults.seo_description} {...text("seo_description")} className={`${inputClass} resize-y`} />
                        </Row>
                        <Row label="Preview" hint="Roughly how it shows on Google, and the shared image.">
                            <div className="grid gap-3 lg:grid-cols-2">
                                <SearchPreview title={data.seo_title || data.title} description={data.seo_description || data.description} />
                                <img src="/images/og-image.jpg" alt="Image shown when the link is shared" className="w-full rounded-xl border border-brand-border" />
                            </div>
                        </Row>
                    </Section>
                )}

                {/* Only while something's changed: one save for every tab. */}
                {(form.isDirty || form.processing) && (
                    <div className="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t border-brand-border bg-brand-bg py-3">
                        <button type="submit" disabled={form.processing} className={primaryButton}>
                            {form.processing ? "Saving…" : "Save changes"}
                        </button>
                        <button type="button" disabled={form.processing} onClick={() => form.reset()} className={secondaryButton}>
                            Discard
                        </button>
                        <span className="text-sm text-brand-muted">Unsaved changes</span>
                    </div>
                )}
            </form>
            {confirmDialog}
        </AdminLayout>
    );
}
