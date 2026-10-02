import { Head, Link, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import { useState } from "react";
import {
    LuArrowLeft,
    LuEllipsis,
    LuExternalLink,
    LuEye,
    LuLogOut,
    LuPencil,
    LuScanLine,
    LuStore,
    LuX,
} from "react-icons/lu";
import { SidebarLinks, useNavigation } from "@/Components/Dashboard/NavMenu";
import {
    navSurface,
    sideLinkClass,
    StatusBanner,
    useSidebarColors,
} from "@/Components/Dashboard/Ui";
import TechsaFooter from "@/Components/TechsaFooter";
import OwnerScanner from "@/Components/Dashboard/OwnerScanner";
import { navIcon } from "@/lib/navIcons";
import { useDocumentTheme } from "@/lib/theme";

/**
 * Admin → "View as owner": whose dashboard this is, whether changes are
 * allowed (read-only by default; the server enforces it), the switch, and
 * the way back to the admin. Red while editing - changes are live.
 */
function ViewAsBanner({ viewAs, blocked }) {
    const editing = viewAs.editing;

    return (
        <div
            role="status"
            className={`sticky top-0 z-30 border-b px-4 py-2.5 text-sm sm:px-6 lg:px-8 ${
                editing ? "border-red-300 bg-red-50 text-red-950" : "border-amber-300 bg-amber-50 text-amber-950"
            }`}
        >
            {/* Phones: the text on its own line, the buttons under it. */}
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                <p className="flex min-w-0 flex-1 items-start gap-2">
                    {editing ? <LuPencil className="mt-0.5 h-4 w-4 shrink-0" /> : <LuEye className="mt-0.5 h-4 w-4 shrink-0" />}
                    <span>
                        {editing ? "Editing" : "Viewing"} <strong>{viewAs.shop_name}</strong> as {viewAs.owner_name}{" "}
                        <span className={editing ? "text-red-800" : "text-amber-800"}>
                            · {editing ? "changes are saved to their shop" : "read-only"}
                        </span>
                    </span>
                </p>
                <div className="flex shrink-0 gap-2">
                    <Link
                        href="/admin/view-as-owner/editing"
                        method="put"
                        as="button"
                        data={{ editing: !editing }}
                        preserveScroll
                        className={`inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold sm:flex-none ${
                            editing ? "border-red-300 bg-white text-red-900 hover:bg-red-100" : "border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                        }`}
                    >
                        {editing ? <LuEye className="h-3.5 w-3.5" /> : <LuPencil className="h-3.5 w-3.5" />}
                        {editing ? "Back to read-only" : "Allow changes"}
                    </Link>
                    <Link
                        href="/admin/view-as-owner/stop"
                        method="post"
                        as="button"
                        className={`inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white sm:flex-none ${
                            editing ? "bg-red-950 hover:bg-red-900" : "bg-amber-950 hover:bg-amber-900"
                        }`}
                    >
                        <LuArrowLeft className="h-3.5 w-3.5" /> Back to admin
                    </Link>
                </div>
            </div>
            {blocked && <p className={`mt-1.5 text-xs font-medium ${editing ? "text-red-900" : "text-amber-900"}`}>{blocked}</p>}
        </div>
    );
}

/**
 * Owner dashboard shell: fixed sidebar on desktop, top bar + bottom tabs on
 * phones. Every page passes the same `shop` summary prop it renders from.
 * The menu itself comes from App\Support\Navigation.
 */
export default function OwnerLayout({
    shop,
    title,
    description,
    actions,
    children,
}) {
    const { props } = usePage();
    const email = props.auth?.user?.email;
    const nav = useNavigation();
    const [scannerOpen, setScannerOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
    const reviewWarningKey = `owner.google-review-warning.dismissed.${shop.id}`;
    const [reviewWarningDismissed, setReviewWarningDismissed] = useState(() => {
        try {
            return window.localStorage.getItem(reviewWarningKey) === "1";
        } catch {
            return false;
        }
    });
    const primaryItems = nav.main.filter((item) => item.mobile_primary);
    const moreItems = nav.main.filter((item) => !item.mobile_primary);
    const moreActive = moreItems.some((item) => item.active);

    // Owner opted in on the Theme page: the dashboard wears the shop's theme too.
    useDocumentTheme(shop.dashboard_theme);
    // Otherwise the admin's sidebar colours (Admin → Settings) apply, as in the admin panel.
    useSidebarColors(!shop.dashboard_theme);

    return (
        <>
            <Head title={`${title} · ${shop.name}`} />

            <div className="flex min-h-dvh flex-col bg-brand-bg">
                {/* Desktop sidebar - soft navy (the theme's deep colour), same surface as the mobile tab bar. */}
                <aside
                    className={`fixed inset-y-0 left-0 z-20 hidden w-64 flex-col lg:flex ${navSurface}`}
                >
                    <div className="flex items-center gap-3 px-5 py-5">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-nav-text/[0.08] text-brand-accent">
                            <LuStore className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-heading text-lg font-semibold leading-tight text-nav-text">
                                {shop.name}
                            </p>
                            <p className="text-xs text-nav-text/55">
                                Owner dashboard
                            </p>
                        </div>
                    </div>

                    <div className="px-3 pb-2">
                        <button
                            type="button"
                            onClick={() => setScannerOpen(true)}
                            className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-3 py-3 text-sm font-semibold text-brand-accent-text shadow-sm transition hover:brightness-95"
                        >
                            <LuScanLine className="h-5 w-5" /> Scan customer
                            card
                        </button>
                    </div>

                    <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                        <SidebarLinks items={nav.main} />
                    </nav>

                    <div className="space-y-1 border-t border-nav-text/[0.08] px-3 py-3">
                        <SidebarLinks items={nav.footer} />
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className={`${sideLinkClass(false)} w-full`}
                        >
                            <LuLogOut className="h-[18px] w-[18px]" />
                            Log out
                        </Link>
                        {email && (
                            <p className="truncate px-3 pt-1 text-xs text-nav-text/45">
                                {email}
                            </p>
                        )}
                    </div>
                </aside>

                {/* Mobile top bar */}
                <header className="sticky top-0 z-20 flex items-center justify-between border-b border-brand-border bg-brand-card/95 px-4 py-3 backdrop-blur lg:hidden">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                            <LuStore className="h-4 w-4" />
                        </span>
                        <p className="truncate font-heading text-base font-semibold text-brand-text">
                            {shop.name}
                        </p>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        aria-label="Log out"
                        className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg"
                    >
                        <LuLogOut className="h-5 w-5" />
                    </Link>
                </header>

                {/* Fills the screen height so the footer sits at the bottom even on short pages. */}
                <main className="flex flex-1 flex-col pb-20 lg:pb-0 lg:pl-64">
                    {props.viewAs && <ViewAsBanner viewAs={props.viewAs} blocked={props.errors?.view_as} />}
                    {/* Full width at every size - pages lay out their own columns inside. */}
                    <div className="w-full flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8 2xl:px-12 2xl:py-10">
                        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h1 className="font-heading text-2xl font-semibold text-brand-text sm:text-3xl">
                                    {title}
                                </h1>
                                {description && (
                                    <p className="mt-1 text-sm text-brand-muted">
                                        {description}
                                    </p>
                                )}
                            </div>
                            {actions}
                        </div>

                        <StatusBanner />

                        {!shop.google_review_url && !reviewWarningDismissed && (
                            <div
                                className="mb-5 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-950"
                                role="status"
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">
                                        Add your Google review link
                                    </span>
                                    <span className="mt-0.5 block text-sm text-amber-900/80">
                                        Customers can rate you in-app now. Add
                                        your Google link to let them share their
                                        review on Google too.
                                    </span>
                                    <Link
                                        href="/dashboard/settings#google-review-url"
                                        className="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-950 underline underline-offset-2 hover:text-amber-800"
                                    >
                                        Set up Google review link{" "}
                                        <LuExternalLink className="h-3.5 w-3.5" />
                                    </Link>
                                </span>
                                <button
                                    type="button"
                                    aria-label="Dismiss Google review link reminder"
                                    onClick={() => {
                                        try {
                                            window.localStorage.setItem(
                                                reviewWarningKey,
                                                "1",
                                            );
                                        } catch {
                                            // Dismiss for this page view if storage is unavailable.
                                        }
                                        setReviewWarningDismissed(true);
                                    }}
                                    className="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-amber-900/70 transition hover:bg-amber-100 hover:text-amber-950"
                                >
                                    <LuX className="h-4 w-4" />
                                </button>
                            </div>
                        )}

                        {children}
                    </div>

                    <TechsaFooter theme="brand" className="pb-4 pt-8" />
                </main>

                {/* Mobile nav keeps the three daily-use pages visible, with the scanner centered. */}
                <nav className="fixed inset-x-0 bottom-0 z-20 flex items-stretch bg-nav pb-[env(safe-area-inset-bottom)] text-nav-text lg:hidden">
                    {primaryItems.slice(0, 2).map((item) => {
                        const Icon = navIcon(item.icon);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={item.active ? "page" : undefined}
                                className={`flex min-w-0 flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-medium transition-colors ${item.active ? "text-brand-accent" : "text-nav-text/60 hover:text-nav-text"}`}
                            >
                                <Icon className="h-5 w-5" />
                                <span className="truncate">{item.label}</span>
                            </Link>
                        );
                    })}
                    <div className="relative flex min-w-14 flex-1 flex-col items-center justify-end pb-2.5">
                        <button
                            type="button"
                            onClick={() => setScannerOpen(true)}
                            aria-label="Scan customer card"
                            title="Scan customer card"
                            className="absolute -top-5 flex h-14 w-14 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text shadow-lg ring-4 ring-brand-bg transition active:scale-95"
                        >
                            <LuScanLine className="h-6 w-6" />
                        </button>
                        <span
                            className="pb-2.5 text-[10px] font-medium text-nav-text/60"
                            aria-hidden="true"
                        >
                            Scan
                        </span>
                    </div>
                    {primaryItems.slice(2).map((item) => {
                        const Icon = navIcon(item.icon);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={item.active ? "page" : undefined}
                                className={`flex min-w-0 flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-medium transition-colors ${item.active ? "text-brand-accent" : "text-nav-text/60 hover:text-nav-text"}`}
                            >
                                <Icon className="h-5 w-5" />
                                <span className="truncate">{item.label}</span>
                            </Link>
                        );
                    })}
                    <button
                        type="button"
                        onClick={() => setMoreOpen((open) => !open)}
                        aria-expanded={moreOpen}
                        aria-label={
                            moreOpen
                                ? "Close more navigation"
                                : "More navigation"
                        }
                        className={`flex min-w-0 flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-medium transition-colors ${moreActive || moreOpen ? "text-brand-accent" : "text-nav-text/60 hover:text-nav-text"}`}
                    >
                        {moreOpen ? (
                            <LuX className="h-5 w-5" />
                        ) : (
                            <LuEllipsis className="h-5 w-5" />
                        )}
                        <span>More</span>
                    </button>
                </nav>

                <AnimatePresence>
                    {moreOpen && (
                        <>
                            <motion.button
                                type="button"
                                aria-label="Close more navigation"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                onClick={() => setMoreOpen(false)}
                                className="fixed inset-0 z-30 bg-black/20 lg:hidden"
                            />
                            <motion.nav
                                aria-label="More dashboard pages"
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 20 }}
                                transition={{ duration: 0.18, ease: "easeOut" }}
                                className="fixed inset-x-3 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 grid grid-cols-2 gap-2 rounded-2xl border border-nav-text/10 bg-nav p-3 text-nav-text shadow-xl lg:hidden"
                            >
                                {moreItems.map((item) => {
                                    const Icon = navIcon(item.icon);

                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            onClick={() => setMoreOpen(false)}
                                            aria-current={
                                                item.active ? "page" : undefined
                                            }
                                            className={`flex min-w-0 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-colors ${item.active ? "bg-brand-accent/15 text-nav-text [&>svg]:text-brand-accent" : "text-nav-text/70 hover:bg-nav-text/5 hover:text-nav-text"}`}
                                        >
                                            <Icon className="h-5 w-5 shrink-0" />
                                            <span className="truncate">
                                                {item.label}
                                            </span>
                                        </Link>
                                    );
                                })}
                            </motion.nav>
                        </>
                    )}
                </AnimatePresence>
            </div>

            {scannerOpen && (
                <OwnerScanner onClose={() => setScannerOpen(false)} />
            )}
        </>
    );
}
