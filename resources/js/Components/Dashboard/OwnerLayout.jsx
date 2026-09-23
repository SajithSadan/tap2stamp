import { Head, Link, usePage } from '@inertiajs/react';
import { LuActivity, LuExternalLink, LuLayoutDashboard, LuLogOut, LuPalette, LuSettings, LuStar, LuStore, LuUserCog, LuUsers } from 'react-icons/lu';
import TechsaFooter from '@/Components/TechsaFooter';
import { useDocumentTheme } from '@/lib/theme';

const NAV = [
    { href: '/dashboard', label: 'Overview', icon: LuLayoutDashboard },
    { href: '/dashboard/customers', label: 'Customers', icon: LuUsers },
    { href: '/dashboard/activity', label: 'Activity', icon: LuActivity },
    { href: '/dashboard/reviews', label: 'Reviews', icon: LuStar },
    { href: '/dashboard/staff', label: 'Staff', icon: LuUserCog },
    { href: '/dashboard/theme', label: 'Theme', icon: LuPalette },
    { href: '/dashboard/settings', label: 'Settings', icon: LuSettings },
];

function isActive(url, href) {
    const path = url.split('?')[0];

    return href === '/dashboard' ? path === '/dashboard' : path.startsWith(href);
}

/**
 * Owner dashboard shell: fixed sidebar on desktop, top bar + bottom tabs on
 * phones. Every page passes the same `shop` summary prop it renders from.
 */
export default function OwnerLayout({ shop, title, description, actions, children }) {
    const { url, props } = usePage();
    const email = props.auth?.user?.email;

    // Owner opted in on the Theme page: the dashboard wears the shop's theme too.
    useDocumentTheme(shop.dashboard_theme);

    return (
        <>
            <Head title={`${title} · ${shop.name}`} />

            <div className="flex min-h-dvh flex-col bg-brand-bg">
                {/* Desktop sidebar */}
                <aside className="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-brand-border bg-brand-card lg:flex">
                    <div className="flex items-center gap-3 px-5 py-5">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                            <LuStore className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-heading text-lg font-semibold leading-tight text-brand-text">{shop.name}</p>
                            <p className="text-xs text-brand-muted">Owner dashboard</p>
                        </div>
                    </div>

                    <nav className="flex-1 space-y-1 px-3 py-2">
                        {NAV.map(({ href, label, icon: Icon }) => {
                            const active = isActive(url, href);

                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
                                        active ? 'bg-brand-accent text-brand-accent-text' : 'text-brand-muted hover:bg-brand-bg hover:text-brand-text'
                                    }`}
                                >
                                    <Icon className="h-[18px] w-[18px]" />
                                    {label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="space-y-1 border-t border-brand-border px-3 py-3">
                        <a
                            href={`/s/${shop.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                        >
                            <LuExternalLink className="h-[18px] w-[18px]" />
                            Customer page
                        </a>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
                        >
                            <LuLogOut className="h-[18px] w-[18px]" />
                            Log out
                        </Link>
                        {email && <p className="truncate px-3 pt-1 text-xs text-brand-muted">{email}</p>}
                    </div>
                </aside>

                {/* Mobile top bar */}
                <header className="sticky top-0 z-20 flex items-center justify-between border-b border-brand-border bg-brand-card/95 px-4 py-3 backdrop-blur lg:hidden">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                            <LuStore className="h-4 w-4" />
                        </span>
                        <p className="truncate font-heading text-base font-semibold text-brand-text">{shop.name}</p>
                    </div>
                    <Link href="/logout" method="post" as="button" aria-label="Log out" className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg">
                        <LuLogOut className="h-5 w-5" />
                    </Link>
                </header>

                {/* Fills the screen height so the footer sits at the bottom even on short pages. */}
                <main className="flex flex-1 flex-col pb-20 lg:pb-0 lg:pl-64">
                    <div className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h1 className="font-heading text-2xl font-semibold text-brand-text sm:text-3xl">{title}</h1>
                                {description && <p className="mt-1 text-sm text-brand-muted">{description}</p>}
                            </div>
                            {actions}
                        </div>

                        {children}
                    </div>

                    <TechsaFooter theme="brand" className="pb-4 pt-8" />
                </main>

                {/* Mobile bottom tabs - scroll sideways on the narrowest phones rather than squashing 7 labels. */}
                <nav className="no-scrollbar fixed inset-x-0 bottom-0 z-20 flex overflow-x-auto border-t border-brand-border bg-brand-card pb-[env(safe-area-inset-bottom)] lg:hidden">
                    {NAV.map(({ href, label, icon: Icon }) => {
                        const active = isActive(url, href);

                        return (
                            <Link
                                key={href}
                                href={href}
                                className={`flex min-w-16 flex-1 shrink-0 flex-col items-center gap-1 py-2.5 text-[10px] font-medium ${active ? 'text-brand-accent' : 'text-brand-muted'}`}
                            >
                                <Icon className="h-5 w-5" />
                                {label}
                            </Link>
                        );
                    })}
                </nav>
            </div>
        </>
    );
}
