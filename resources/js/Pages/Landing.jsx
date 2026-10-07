import { Head, Link } from "@inertiajs/react";
import { useState } from "react";
import { LuArrowRight, LuCheck, LuGift, LuPlay } from "react-icons/lu";
import { Wordmark } from "@/Components/AuthShell";

/**
 * "/" for visitors: title + description, a YouTube video, then pricing in
 * the visitor's own currency (picked on the server from their IP, see
 * LandingController). All of it is edited in Admin → Landing page.
 */

const startFree = "/register";

/**
 * Only a thumbnail until it's clicked, then the real (privacy-enhanced)
 * player - so the page doesn't load YouTube's scripts for every visitor.
 */
function Video({ id, title }) {
    const [playing, setPlaying] = useState(false);

    return (
        <div className="relative aspect-video overflow-hidden rounded-3xl bg-brand-deep">
            {playing ? (
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`}
                    title={title}
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="absolute inset-0 h-full w-full"
                />
            ) : (
                <button type="button" onClick={() => setPlaying(true)} className="group absolute inset-0 h-full w-full" aria-label="Play video">
                    <img src={`https://i.ytimg.com/vi/${id}/hqdefault.jpg`} alt="" className="h-full w-full object-cover opacity-90 transition group-hover:opacity-100" />
                    <span className="absolute left-1/2 top-1/2 flex h-20 w-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text transition group-hover:scale-105">
                        <LuPlay className="ml-1 h-8 w-8 fill-current" />
                    </span>
                </button>
            )}
        </div>
    );
}

function PlanCard({ plan }) {
    const dark = plan.highlighted;

    return (
        <article
            className={`flex flex-col rounded-3xl p-7 ${
                dark ? "bg-brand-deep text-white" : "border border-brand-border bg-brand-card text-brand-text"
            }`}
        >
            {plan.badge && (
                <span className="mb-4 self-start rounded-full bg-brand-accent px-3.5 py-1.5 text-xs font-bold text-brand-deep">{plan.badge}</span>
            )}
            <h3 className="font-heading text-2xl font-bold">{plan.name}</h3>
            {plan.description && <p className={`mt-3 text-sm leading-relaxed ${dark ? "text-white/70" : "text-brand-muted"}`}>{plan.description}</p>}
            {plan.note && (
                <p className="mt-5 flex items-start gap-1.5 text-sm font-semibold text-brand-accent">
                    <LuGift className="mt-0.5 h-4 w-4 shrink-0" /> {plan.note}
                </p>
            )}

            {plan.price !== null && (
                <p className="mt-6 flex items-baseline gap-1.5">
                    <span className={`font-heading text-4xl font-bold tabular-nums ${dark ? "text-brand-accent" : ""}`}>
                        {plan.symbol}
                        {plan.price}
                    </span>
                    {plan.period && <span className={`text-sm font-medium ${dark ? "text-white/60" : "text-brand-muted"}`}>{plan.period}</span>}
                </p>
            )}
            {plan.billing_note && <p className={`mt-2 text-xs ${dark ? "text-white/50" : "text-brand-muted"}`}>{plan.billing_note}</p>}

            {plan.features.length > 0 && (
                <ul className="mt-7 space-y-2.5 text-[15px]">
                    {plan.features.map((feature) => (
                        <li key={feature} className="flex items-start gap-2.5">
                            <LuCheck className="mt-1 h-4 w-4 shrink-0 text-brand-accent" strokeWidth={3} />
                            <span>{feature}</span>
                        </li>
                    ))}
                </ul>
            )}

            <Link
                href={startFree}
                className={`mt-8 inline-flex items-center justify-center rounded-full px-6 py-3 text-sm font-semibold transition ${
                    dark
                        ? "bg-brand-accent text-brand-accent-text hover:brightness-105"
                        : "border-2 border-brand-accent text-brand-text hover:bg-brand-accent hover:text-brand-accent-text"
                }`}
            >
                {plan.cta_label}
            </Link>
        </article>
    );
}

const GRID = { 1: "md:grid-cols-1 max-w-md", 2: "md:grid-cols-2 max-w-3xl", 3: "md:grid-cols-3 max-w-6xl", 4: "md:grid-cols-2 xl:grid-cols-4 max-w-7xl" };

export default function Landing({ title, description, youtube_id, plans }) {
    return (
        <>
            <Head title="Digital loyalty cards for independents" />

            <div className="min-h-dvh bg-white text-brand-text">
                <header className="sticky top-0 z-20 border-b border-brand-border bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Wordmark />
                        <nav className="flex items-center gap-2 sm:gap-4">
                            <a href="#pricing" className="hidden text-sm font-medium text-brand-muted hover:text-brand-text sm:inline">
                                Pricing
                            </a>
                            <Link href="/login" className="px-2 text-sm font-medium text-brand-text hover:text-brand-accent">
                                Log in
                            </Link>
                            <Link href={startFree} className="rounded-full bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text hover:brightness-105">
                                Start Free
                            </Link>
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-3xl px-4 pb-12 pt-16 text-center sm:px-6 sm:pt-24">
                        <h1 className="font-heading text-4xl font-bold leading-tight tracking-tight text-brand-deep sm:text-6xl">{title}</h1>
                        {description && <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-brand-muted">{description}</p>}
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Link
                                href={startFree}
                                className="inline-flex items-center gap-2 rounded-full bg-brand-accent px-7 py-3.5 font-semibold text-brand-accent-text hover:brightness-105"
                            >
                                Start Free <LuArrowRight className="h-4 w-4" />
                            </Link>
                            <a
                                href="#pricing"
                                className="inline-flex items-center rounded-full border-2 border-brand-border px-7 py-3.5 font-semibold text-brand-text hover:border-brand-accent"
                            >
                                See pricing
                            </a>
                        </div>
                    </section>

                    {youtube_id && (
                        <section className="mx-auto max-w-5xl px-4 pb-16 sm:px-6">
                            <Video id={youtube_id} title={title} />
                        </section>
                    )}

                    <section id="pricing" className="scroll-mt-20 bg-brand-bg px-4 py-20 sm:px-6">
                        <h2 className="text-center font-heading text-3xl font-bold text-brand-deep sm:text-4xl">Pricing</h2>
                        <div className={`mx-auto mt-12 grid items-start gap-6 ${GRID[plans.length] ?? GRID[3]}`}>
                            {plans.map((plan) => (
                                <PlanCard key={plan.name} plan={plan} />
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="border-t border-brand-border">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-brand-muted sm:px-6">
                        <span>© {new Date().getFullYear()} Tada Tap</span>
                        <Link href="/login" className="hover:text-brand-text">
                            Log in
                        </Link>
                    </div>
                </footer>
            </div>
        </>
    );
}
