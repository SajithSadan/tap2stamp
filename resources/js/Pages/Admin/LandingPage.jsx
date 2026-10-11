import { useForm } from "@inertiajs/react";
import { useConfirm } from "@/Components/ConfirmDialog";
import { LuArrowDown, LuArrowUp, LuExternalLink, LuPlus, LuRotateCcw, LuTrash2, LuX } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton, Switch } from "@/Components/Dashboard/Ui";

/**
 * Admin → Landing page: the public page at "/" - title, description, a
 * YouTube video and the pricing plans, each with a price per offered
 * currency. Visitors see their own country's currency when it's offered,
 * else the default (App\Support\LandingPage). One form, one save.
 */

// Same link shapes as LandingPage::youtubeId().
const YOUTUBE = /^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/)|youtu\.be\/|youtube-nocookie\.com\/embed\/)([A-Za-z0-9_-]{11})/;

const blankPlan = (currencies) => ({
    name: "",
    description: "",
    note: "",
    badge: "",
    highlighted: false,
    cta_label: "Start Free",
    period: "/ month",
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
    }));

function Field({ label, hint, error, children, className = "" }) {
    return (
        <label className={`block min-w-0 ${className}`}>
            <span className="mb-1.5 block text-sm font-medium text-brand-text">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-brand-muted">{hint}</span>}
            <FieldError message={error} />
        </label>
    );
}

/** "54 / 60": a length hint that turns amber past what search results show. */
function Count({ value, ideal }) {
    const length = value.trim().length;
    return <span className={length > ideal ? "text-amber-700" : ""}>{length} / {ideal} characters shown in search results</span>;
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

export default function LandingPage({ content, defaults, currencyOptions, limits, ipLookup }) {
    const form = useForm(toFormData(content));
    const [confirm, confirmDialog] = useConfirm();

    // The suggested copy and plans, into the form - nothing changes until Save.
    async function loadDefaults() {
        const ok = await confirm({
            title: "Load the default content?",
            message: "The whole form is replaced with the suggested search title and description, hero, trust banner, pricing plans and FAQ. Nothing is saved until you press Save - Discard brings back what's live.",
            confirmLabel: "Load defaults",
        });
        if (ok) form.setData({ ...toFormData(defaults), youtube_url: form.data.youtube_url });
    }
    const { data, errors } = form;
    const symbol = (code) => currencyOptions.find((c) => c.code === code)?.symbol ?? `${code} `;
    const videoId = data.youtube_url.match(YOUTUBE)?.[1];

    const setPlan = (i, patch) => form.setData("plans", data.plans.map((p, j) => (j === i ? { ...p, ...patch } : p)));
    const setFaq = (i, patch) => form.setData("faqs", data.faqs.map((f, j) => (j === i ? { ...f, ...patch } : f)));
    const moveFaq = (i, by) => {
        const faqs = [...data.faqs];
        [faqs[i], faqs[i + by]] = [faqs[i + by], faqs[i]];
        form.setData("faqs", faqs);
    };
    const movePlan = (i, by) => {
        const plans = [...data.plans];
        [plans[i], plans[i + by]] = [plans[i + by], plans[i]];
        form.setData("plans", plans);
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

    const text = (key) => ({ value: data[key], onChange: (e) => form.setData(key, e.target.value), className: inputClass });
    const planText = (i, key) => ({ value: data.plans[i][key], onChange: (e) => setPlan(i, { [key]: e.target.value }), className: inputClass });

    return (
        <AdminLayout
            title="Landing page"
            description="The public page at your app's home: what visitors read, watch and pay."
            actions={
                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={loadDefaults} className={secondaryButton}>
                        <LuRotateCcw className="h-4 w-4" /> Load default content
                    </button>
                    {data.currencies.map((code) => (
                        <a key={code} href={`/?preview=1&currency=${code}`} target="_blank" rel="noreferrer" className={secondaryButton}>
                            <LuExternalLink className="h-4 w-4" /> Preview {code}
                        </a>
                    ))}
                </div>
            }
        >
            <form onSubmit={submit} noValidate className="space-y-4">
                <Panel
                    title="Search & sharing"
                    description="How the page appears on Google and when its link is shared (WhatsApp, Facebook, LinkedIn). Written for every country - no prices, they differ by country."
                >
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div className="space-y-4">
                            <Field label="Page title" hint={<Count value={data.seo_title} ideal={60} />} error={errors.seo_title}>
                                <input type="text" maxLength={70} placeholder={defaults.seo_title} {...text("seo_title")} />
                            </Field>
                            <Field label="Description" hint={<Count value={data.seo_description} ideal={160} />} error={errors.seo_description}>
                                <textarea rows={3} maxLength={200} placeholder={defaults.seo_description} {...text("seo_description")} className={`${inputClass} resize-y`} />
                            </Field>
                        </div>
                        <div className="space-y-3">
                            <SearchPreview title={data.seo_title || data.title} description={data.seo_description || data.description} />
                            <img src="/images/og-image.jpg" alt="Image shown when the link is shared" className="w-full rounded-xl border border-brand-border" />
                        </div>
                    </div>
                </Panel>

                <Panel title="Hero" description="The top of the page.">
                    <div className="space-y-4">
                        <Field label="Badge above the heading (optional)" error={errors.kicker}>
                            <input type="text" maxLength={120} placeholder="Digital Loyalty & Growth Engine for Retail, Dining & Salons" {...text("kicker")} />
                        </Field>
                        <Field label="Heading" error={errors.title}>
                            <input type="text" maxLength={120} {...text("title")} />
                        </Field>
                        <Field label="Subtitle" error={errors.description}>
                            <textarea rows={3} maxLength={400} {...text("description")} className={`${inputClass} resize-y`} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Main button" hint="Opens sign-up (/register)." error={errors.primary_cta}>
                                <input type="text" maxLength={40} placeholder="Start 1-Month Free Trial" {...text("primary_cta")} />
                            </Field>
                            <Field label="Demo button (optional)" hint="Scrolls to the video and plays it. Hidden without a video." error={errors.demo_cta}>
                                <input type="text" maxLength={40} placeholder="Watch 60-Sec Demo" {...text("demo_cta")} />
                            </Field>
                        </div>
                        <Field label="YouTube video" hint="Paste the video's link. Leave empty for no video." error={errors.youtube_url}>
                            <input type="url" placeholder="https://www.youtube.com/watch?v=…" {...text("youtube_url")} />
                        </Field>
                        {videoId && (
                            <img src={`https://i.ytimg.com/vi/${videoId}/hqdefault.jpg`} alt="Video thumbnail" className="aspect-video w-60 rounded-xl object-cover" />
                        )}
                    </div>
                </Panel>

                <Panel title="Trust banner" description="The dark strip under the video. Leave it all empty to hide it.">
                    <div className="space-y-4">
                        <Field label="Title" error={errors.trust_title}>
                            <input type="text" maxLength={120} {...text("trust_title")} />
                        </Field>
                        <Field label="Text" error={errors.trust_description}>
                            <textarea rows={3} maxLength={400} {...text("trust_description")} className={`${inputClass} resize-y`} />
                        </Field>
                        <Field label="Badges" hint={`One per line, up to ${limits.trustPoints}. Each gets a tick.`} error={errors.trust_points}>
                            <textarea rows={3} {...text("trust_points_text")} className={`${inputClass} resize-y`} />
                        </Field>
                    </div>
                </Panel>

                <Panel title="Pricing heading">
                    <div className="space-y-4">
                        <Field label="Title" error={errors.pricing_title}>
                            <input type="text" maxLength={120} placeholder="Simple, Transparent Annual Pricing" {...text("pricing_title")} />
                        </Field>
                        <Field label="Subtitle (optional)" error={errors.pricing_subtitle}>
                            <input type="text" maxLength={300} {...text("pricing_subtitle")} />
                        </Field>
                    </div>
                </Panel>

                <Panel
                    title="Currencies"
                    description="Visitors see their own country's currency when it's offered here, otherwise the default - and can switch between the offered ones on the page."
                >
                    {!ipLookup && (
                        <p className="mb-4 rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                            Add <code className="font-mono">IPINFO_TOKEN</code> to .env to detect visitors' countries. Until then everyone sees the default
                            currency.
                        </p>
                    )}
                    <div className="flex flex-wrap items-center gap-2">
                        {data.currencies.map((code) => (
                            <span key={code} className="inline-flex items-center gap-1.5 rounded-full border border-brand-border bg-brand-bg py-1 pl-3 pr-1 text-sm">
                                <span className="font-semibold text-brand-text">{code}</span>
                                <span className="text-brand-muted">{symbol(code).trim()}</span>
                                {data.default_currency === code && <span className="text-xs font-medium text-brand-accent">default</span>}
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
                        {data.currencies.length < limits.currencies && (
                            <select value="" onChange={(e) => addCurrency(e.target.value)} aria-label="Add a currency" className={`${inputClass} !w-auto`}>
                                <option value="">+ Add currency</option>
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
                    <FieldError message={errors.currencies} />
                    <div className="mt-4 max-w-xs">
                        <Field label="Default currency" hint="For countries whose currency isn't offered." error={errors.default_currency}>
                            <select {...text("default_currency")}>
                                {data.currencies.map((code) => (
                                    <option key={code} value={code}>
                                        {code}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                </Panel>

                {data.plans.map((plan, i) => (
                    <Panel
                        key={i}
                        title={plan.name || `Plan ${i + 1}`}
                        action={
                            <div className="flex items-center gap-1">
                                <button type="button" disabled={i === 0} onClick={() => movePlan(i, -1)} aria-label="Move left" className={`${secondaryButton} !px-2`}>
                                    <LuArrowUp className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    disabled={i === data.plans.length - 1}
                                    onClick={() => movePlan(i, 1)}
                                    aria-label="Move right"
                                    className={`${secondaryButton} !px-2`}
                                >
                                    <LuArrowDown className="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    disabled={data.plans.length === 1}
                                    onClick={() => form.setData("plans", data.plans.filter((_, j) => j !== i))}
                                    aria-label="Remove plan"
                                    className={`${secondaryButton} !px-2 hover:!text-red-600`}
                                >
                                    <LuTrash2 className="h-4 w-4" />
                                </button>
                            </div>
                        }
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Name" error={errors[`plans.${i}.name`]}>
                                <input type="text" maxLength={40} placeholder="Starter" {...planText(i, "name")} />
                            </Field>
                            <Field label="Badge (optional)" hint="A short pill on the card, e.g. Most Popular." error={errors[`plans.${i}.badge`]}>
                                <input type="text" maxLength={40} placeholder="Most Popular" {...planText(i, "badge")} />
                            </Field>
                            <Field label="Who it's for" hint="Under the plan name, e.g. Best for Cafes, Restaurants & Busy Spas." className="sm:col-span-2" error={errors[`plans.${i}.description`]}>
                                <input type="text" maxLength={200} {...planText(i, "description")} />
                            </Field>
                            <Field label="Offer line (optional)" hint="Shown with a gift icon above the price." className="sm:col-span-2" error={errors[`plans.${i}.note`]}>
                                <input type="text" maxLength={120} placeholder="Free for Year 1 when you buy hardware" {...planText(i, "note")} />
                            </Field>

                            <div className="sm:col-span-2">
                                <span className="mb-1.5 block text-sm font-medium text-brand-text">Price</span>
                                <div className="flex flex-wrap gap-3">
                                    {data.currencies.map((code) => (
                                        <div key={code} className="w-36">
                                            <div className="flex items-stretch overflow-hidden rounded-xl border border-brand-border bg-brand-card focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10">
                                                <span className="flex items-center border-r border-brand-border bg-brand-bg px-2.5 text-xs font-semibold text-brand-muted">{code}</span>
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    aria-label={`${code} price`}
                                                    value={plan.prices[code] ?? ""}
                                                    onChange={(e) => setPlan(i, { prices: { ...plan.prices, [code]: e.target.value } })}
                                                    placeholder="0.00"
                                                    className="w-full min-w-0 bg-transparent px-2.5 py-2.5 text-sm tabular-nums text-brand-text outline-none"
                                                />
                                            </div>
                                            <FieldError message={errors[`plans.${i}.prices.${code}`]} />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <Field label="After the price" hint='e.g. "/ year"' error={errors[`plans.${i}.period`]}>
                                <input type="text" maxLength={30} {...planText(i, "period")} />
                            </Field>
                            <Field label="Under the price (optional)" hint='e.g. "Billed annually"' error={errors[`plans.${i}.billing_note`]}>
                                <input type="text" maxLength={60} {...planText(i, "billing_note")} />
                            </Field>
                            <Field
                                label="Features"
                                hint={`One per line, up to ${limits.features}. "Title (detail)" shows the detail smaller; a line ending in ":" is a heading.`} className="sm:col-span-2" error={errors[`plans.${i}.features`]}>
                                <textarea rows={6} {...planText(i, "features_text")} className={`${inputClass} resize-y`} />
                            </Field>
                            <Field label="Button text" error={errors[`plans.${i}.cta_label`]}>
                                <input type="text" maxLength={30} {...planText(i, "cta_label")} />
                            </Field>
                            <div className="self-end pb-1">
                                <Switch
                                    checked={plan.highlighted}
                                    onChange={(on) => setPlan(i, { highlighted: on })}
                                    label="Highlight this plan"
                                    description="Dark card, like the most popular one."
                                />
                            </div>
                        </div>
                    </Panel>
                ))}

                {data.plans.length < limits.plans && (
                    <button
                        type="button"
                        onClick={() => form.setData("plans", [...data.plans, blankPlan(data.currencies)])}
                        className="flex w-full items-center justify-center gap-1.5 rounded-2xl border border-dashed border-brand-border py-3 text-sm font-medium text-brand-muted hover:border-brand-accent hover:text-brand-text"
                    >
                        <LuPlus className="h-4 w-4" /> Add plan
                    </button>
                )}
                <FieldError message={errors.plans} />

                <Panel
                    title="FAQ"
                    description="Questions and answers at the bottom of the page. Google also reads them (FAQ structured data). Leave a row empty to remove it."
                >
                    <div className="space-y-4">
                        <Field label="Heading" error={errors.faq_title}>
                            <input type="text" maxLength={120} placeholder="Frequently asked questions" {...text("faq_title")} />
                        </Field>
                        {data.faqs.map((faq, i) => (
                            <div key={i} className="rounded-xl border border-brand-border p-4">
                                <div className="flex items-start gap-2">
                                    <span className="mt-2.5 w-6 shrink-0 text-sm font-semibold tabular-nums text-brand-muted">{i + 1}.</span>
                                    <div className="min-w-0 flex-1 space-y-2">
                                        <Field label={<span className="sr-only">Question {i + 1}</span>} error={errors[`faqs.${i}.question`]}>
                                            <input type="text" maxLength={200} placeholder="Question" value={faq.question} onChange={(e) => setFaq(i, { question: e.target.value })} className={`${inputClass} font-medium`} />
                                        </Field>
                                        <Field label={<span className="sr-only">Answer {i + 1}</span>} error={errors[`faqs.${i}.answer`]}>
                                            <textarea rows={3} maxLength={1000} placeholder="Answer" value={faq.answer} onChange={(e) => setFaq(i, { answer: e.target.value })} className={`${inputClass} resize-y`} />
                                        </Field>
                                    </div>
                                    <div className="flex shrink-0 flex-col gap-1">
                                        <button type="button" onClick={() => moveFaq(i, -1)} disabled={i === 0} aria-label="Move up" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text disabled:opacity-30">
                                            <LuArrowUp className="h-4 w-4" />
                                        </button>
                                        <button type="button" onClick={() => moveFaq(i, 1)} disabled={i === data.faqs.length - 1} aria-label="Move down" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text disabled:opacity-30">
                                            <LuArrowDown className="h-4 w-4" />
                                        </button>
                                        <button type="button" onClick={() => form.setData("faqs", data.faqs.filter((_, j) => j !== i))} aria-label="Remove question" className="rounded-lg p-1.5 text-brand-muted hover:bg-red-500/10 hover:text-red-700">
                                            <LuTrash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                        {data.faqs.length < limits.faqs && (
                            <button
                                type="button"
                                onClick={() => form.setData("faqs", [...data.faqs, { question: "", answer: "" }])}
                                className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-brand-border py-2.5 text-sm font-medium text-brand-muted hover:border-brand-accent hover:text-brand-text"
                            >
                                <LuPlus className="h-4 w-4" /> Add question
                            </button>
                        )}
                        <FieldError message={errors.faqs} />
                    </div>
                </Panel>

                {(form.isDirty || form.processing) && (
                    <div className="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t border-brand-border bg-brand-bg py-3">
                        <button type="submit" disabled={form.processing} className={primaryButton}>
                            {form.processing ? "Saving…" : "Save landing page"}
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
