import { Head, Link } from "@inertiajs/react";
import { useRef, useState } from "react";
import {
    LuArrowRight,
    LuCheck,
    LuCircleCheck,
    LuGift,
    LuPlay,
    LuSparkles,
} from "react-icons/lu";
import { Wordmark } from "@/Components/AuthShell";

/**
 * "/" for visitors: hero (badge, heading, subtitle, trial + demo buttons),
 * the YouTube demo, a free-trial trust banner, then pricing - in the
 * visitor's own currency only (picked on the server from their IP, see
 * LandingController; other countries' prices are never sent). All of it is
 * edited in Admin → Landing page (App\Support\LandingPage).
 */

const REGISTER = "/register";

/** 2999 → "2,999" (Indian grouping for rupees); prices with decimals stay as typed. */
function formatPrice(price, code) {
    if (price === null || price === undefined) return "";
    if (!/^\d+$/.test(String(price))) return price;

    return Number(price).toLocaleString(code === "INR" ? "en-IN" : "en-GB");
}

/**
 * Only a thumbnail until it's played, then the real (privacy-enhanced)
 * player - so the page doesn't load YouTube's scripts for every visitor.
 * `playing` is lifted so the hero's "Watch demo" button can start it.
 */
function Video({ id, title, playing, onPlay }) {
    return (
        <div className="relative aspect-video overflow-hidden rounded-3xl border border-brand-border bg-brand-deep shadow-2xl shadow-brand-deep/15">
            {playing ? (
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`}
                    title={title}
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="absolute inset-0 h-full w-full"
                />
            ) : (
                <button
                    type="button"
                    onClick={onPlay}
                    className="group absolute inset-0 h-full w-full"
                    aria-label="Play video"
                >
                    <img
                        src={`https://i.ytimg.com/vi/${id}/hqdefault.jpg`}
                        alt=""
                        className="h-full w-full object-cover opacity-90 transition group-hover:opacity-100"
                    />
                    <span className="absolute left-1/2 top-1/2 flex h-20 w-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text shadow-lg transition group-hover:scale-105">
                        <LuPlay className="ml-1 h-8 w-8 fill-current" />
                    </span>
                </button>
            )}
        </div>
    );
}

/** Free-trial reassurance, right under the video. */
function TrustBanner({ title, description, points }) {
    if (!title && !description && points.length === 0) return null;

    return (
        <section className="mx-auto max-w-5xl px-4 pb-16 sm:px-6">
            <div className="rounded-3xl bg-brand-deep px-6 py-8 text-white sm:px-10 sm:py-10">
                <div className="flex items-start gap-4">
                    <span className="hidden h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-accent text-brand-accent-text sm:flex">
                        <LuGift className="h-6 w-6" />
                    </span>
                    <div className="min-w-0">
                        {title && (
                            <h2 className="font-heading text-2xl font-bold sm:text-3xl">
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p className="mt-3 max-w-3xl leading-relaxed text-white/75">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {points.length > 0 && (
                    <ul className="mt-6 flex flex-wrap gap-2.5 sm:pl-16">
                        {points.map((point) => (
                            <li
                                key={point}
                                className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-2 text-sm font-medium"
                            >
                                <LuCircleCheck className="h-4 w-4 shrink-0 text-brand-accent" />
                                {point}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

/** A feature line: "Everything in Growth, plus:" is a small heading; "Title (detail)" shows the detail smaller. */
function Feature({ text, dark }) {
    if (text.trim().endsWith(":")) {
        return (
            <li
                className={`pt-1 text-xs font-semibold uppercase tracking-wide ${dark ? "text-brand-accent" : "text-brand-muted"}`}
            >
                {text}
            </li>
        );
    }

    const match = text.match(/^(.*?)\s*\((.+)\)\s*$/);

    return (
        <li className="flex items-start gap-2.5">
            <LuCheck
                className="mt-1 h-4 w-4 shrink-0 text-brand-accent"
                strokeWidth={3}
            />
            <span>
                {match ? match[1] : text}
                {match && (
                    <span
                        className={`mt-0.5 block text-[13px] leading-snug ${dark ? "text-white/60" : "text-brand-muted"}`}
                    >
                        {match[2]}
                    </span>
                )}
            </span>
        </li>
    );
}

function PlanCard({ plan, currency }) {
    const dark = plan.highlighted;
    const shown = { price: plan.price, symbol: plan.symbol };

    return (
        <article
            className={`relative flex flex-col rounded-3xl p-7 ${
                dark
                    ? "bg-brand-deep text-white shadow-2xl shadow-brand-deep/20 ring-2 ring-brand-accent md:-my-3 md:py-10"
                    : "border border-brand-border bg-brand-card text-brand-text"
            }`}
        >
            {plan.badge && (
                <span className="absolute -top-3.5 left-7 inline-flex items-center gap-1.5 rounded-full bg-brand-accent px-3.5 py-1.5 text-xs font-bold text-brand-accent-text shadow-sm">
                    <LuSparkles className="h-3.5 w-3.5" /> {plan.badge}
                </span>
            )}
            <h3 className="font-heading text-2xl font-bold">{plan.name}</h3>
            {plan.description && (
                <p
                    className={`mt-2 text-sm leading-relaxed ${dark ? "text-white/70" : "text-brand-muted"}`}
                >
                    {plan.description}
                </p>
            )}
            {plan.note && (
                <p className="mt-4 flex items-start gap-1.5 text-sm font-semibold text-brand-accent">
                    <LuGift className="mt-0.5 h-4 w-4 shrink-0" /> {plan.note}
                </p>
            )}

            {shown.price !== null && (
                <p className="mt-6 flex flex-wrap items-baseline gap-x-1.5">
                    <span
                        className={`font-heading text-4xl font-bold tabular-nums ${dark ? "text-brand-accent" : "text-brand-deep"}`}
                    >
                        {shown.symbol}
                        {formatPrice(shown.price, currency)}
                    </span>
                    {plan.period && (
                        <span
                            className={`text-sm font-medium ${dark ? "text-white/60" : "text-brand-muted"}`}
                        >
                            {plan.period}
                        </span>
                    )}
                </p>
            )}
            {plan.billing_note && (
                <p
                    className={`mt-1.5 text-xs ${dark ? "text-white/55" : "text-brand-muted"}`}
                >
                    {plan.billing_note}
                </p>
            )}

            <Link
                href={REGISTER}
                className={`mt-6 inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold transition ${
                    dark
                        ? "bg-brand-accent text-brand-accent-text hover:brightness-105"
                        : "border-2 border-brand-accent text-brand-text hover:bg-brand-accent hover:text-brand-accent-text"
                }`}
            >
                {plan.cta_label}
            </Link>

            {plan.features.length > 0 && (
                <ul
                    className={`mt-7 space-y-3 border-t pt-6 text-[15px] ${dark ? "border-white/10" : "border-brand-border"}`}
                >
                    {plan.features.map((feature) => (
                        <Feature key={feature} text={feature} dark={dark} />
                    ))}
                </ul>
            )}
        </article>
    );
}

const GRID = {
    1: "md:grid-cols-1 max-w-md",
    2: "md:grid-cols-2 max-w-3xl",
    3: "md:grid-cols-3 max-w-6xl",
    4: "md:grid-cols-2 xl:grid-cols-4 max-w-7xl",
};

export default function Landing({
    kicker,
    title,
    description,
    primary_cta,
    demo_cta,
    youtube_id,
    trust_title,
    trust_description,
    trust_points = [],
    pricing_title,
    pricing_subtitle,
    currency,
    plans,
}) {
    const [playing, setPlaying] = useState(false);
    const demo = useRef(null);

    // "Watch demo": scroll to the video and start it (the click counts as the gesture autoplay needs).
    function watchDemo() {
        demo.current?.scrollIntoView({ behavior: "smooth", block: "center" });
        setPlaying(true);
    }

    return (
        <>
            <Head title={title} />

            <div className="min-h-dvh bg-white text-brand-text">
                <header className="sticky top-0 z-20 border-b border-brand-border bg-white/95 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Wordmark />
                        <nav className="flex items-center gap-2 sm:gap-4">
                            <a
                                href="#pricing"
                                className="hidden text-sm font-medium text-brand-muted hover:text-brand-text sm:inline"
                            >
                                Pricing
                            </a>
                            <Link
                                href="/login"
                                className="px-2 text-sm font-medium text-brand-text hover:text-brand-accent"
                            >
                                Log in
                            </Link>
                            <Link
                                href={REGISTER}
                                className="rounded-full bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text hover:brightness-105"
                            >
                                Start Free Trial
                            </Link>
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-4xl px-4 pb-12 pt-14 text-center sm:px-6 sm:pt-20">
                        {kicker && (
                            <p className="inline-flex items-center gap-2 rounded-full bg-brand-accent/10 px-4 py-1.5 text-xs font-semibold text-brand-deep ring-1 ring-brand-accent/30 sm:text-sm">
                                <LuSparkles className="h-4 w-4 shrink-0 text-brand-accent" />
                                {kicker}
                            </p>
                        )}
                        <h1 className="mt-6 font-heading text-4xl font-bold leading-[1.1] tracking-tight text-brand-deep sm:text-6xl">
                            {title}
                        </h1>
                        {description && (
                            <p className="mx-auto mt-6 max-w-3xl text-lg leading-relaxed text-brand-muted">
                                {description}
                            </p>
                        )}
                        <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link
                                href={REGISTER}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-accent px-7 py-3.5 font-semibold text-brand-accent-text shadow-lg shadow-brand-accent/25 hover:brightness-105 sm:w-auto"
                            >
                                {primary_cta}{" "}
                                <LuArrowRight className="h-4 w-4" />
                            </Link>
                            {youtube_id && demo_cta && (
                                <button
                                    type="button"
                                    onClick={watchDemo}
                                    className="inline-flex w-full items-center justify-center gap-2 rounded-full border-2 border-brand-border px-7 py-3.5 font-semibold text-brand-text hover:border-brand-accent sm:w-auto"
                                >
                                    <LuPlay className="h-4 w-4 fill-current text-brand-accent" />{" "}
                                    {demo_cta}
                                </button>
                            )}
                        </div>
                    </section>

                    {youtube_id && (
                        <section
                            id="demo"
                            ref={demo}
                            className="mx-auto max-w-5xl scroll-mt-24 px-4 pb-10 sm:px-6"
                        >
                            <Video
                                id={youtube_id}
                                title={title}
                                playing={playing}
                                onPlay={() => setPlaying(true)}
                            />
                        </section>
                    )}

                    <TrustBanner
                        title={trust_title}
                        description={trust_description}
                        points={trust_points}
                    />

                    <section
                        id="pricing"
                        className="scroll-mt-20 bg-brand-bg px-4 py-20 sm:px-6"
                    >
                        <div className="mx-auto max-w-3xl text-center">
                            <h2 className="font-heading text-3xl font-bold text-brand-deep sm:text-4xl">
                                {pricing_title}
                            </h2>
                            {pricing_subtitle && (
                                <p className="mt-4 text-lg text-brand-muted">
                                    {pricing_subtitle}
                                </p>
                            )}
                        </div>
                        {/* Just which currency these are - never the other countries' prices. */}
                        {/* <p className="mt-4 text-center text-sm text-brand-muted">
                            Prices in {plans[0]?.symbol?.trim() && plans[0].symbol.trim() !== currency ? `${plans[0].symbol.trim()} ` : ""}
                            {currency}
                        </p> */}
                        <div
                            className={`mx-auto mt-14 grid items-start gap-8 md:gap-6 ${GRID[plans.length] ?? GRID[3]}`}
                        >
                            {plans.map((plan) => (
                                <PlanCard
                                    key={plan.name}
                                    plan={plan}
                                    currency={currency}
                                />
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="border-t border-brand-border">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-brand-muted sm:px-6">
                        <span>© {new Date().getFullYear()} TaDa Tap</span>
                        <Link href="/login" className="hover:text-brand-text">
                            Log in
                        </Link>
                    </div>
                </footer>
            </div>
        </>
    );
}
