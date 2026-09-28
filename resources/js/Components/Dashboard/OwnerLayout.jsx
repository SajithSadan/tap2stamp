import { Head, Link, usePage } from '@inertiajs/react';
import { LuLogOut, LuStore } from 'react-icons/lu';
import { SidebarLinks, TabLinks, useNavigation } from '@/Components/Dashboard/NavMenu';
import { navSurface, sideLinkClass, StatusBanner } from '@/Components/Dashboard/Ui';
import TechsaFooter from '@/Components/TechsaFooter';
import { useDocumentTheme } from '@/lib/theme';

/**
 * Owner dashboard shell: fixed sidebar on desktop, top bar + bottom tabs on
 * phones. Every page passes the same `shop` summary prop it renders from.
 * The menu itself comes from App\Support\Navigation.
 */
export default function OwnerLayout({ shop, title, description, actions, children }) {
    const { props } = usePage();
    const email = props.auth?.user?.email;
    const nav = useNavigation();

    // Owner opted in on the Theme page: the dashboard wears the shop's theme too.
    useDocumentTheme(shop.dashboard_theme);

    return (
        <>
            <Head title={`${title} · ${shop.name}`} />

            <div className="flex min-h-dvh flex-col bg-brand-bg">
                {/* Desktop sidebar - soft navy (the theme's deep colour), same surface as the mobile tab bar. */}
                <aside className={`fixed inset-y-0 left-0 z-20 hidden w-64 flex-col lg:flex ${navSurface}`}>
                    <div className="flex items-center gap-3 px-5 py-5">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/[0.08] text-brand-accent">
                            <LuStore className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-heading text-lg font-semibold leading-tight text-white">{shop.name}</p>
                            <p className="text-xs text-white/55">Owner dashboard</p>
                        </div>
                    </div>

                    <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                        <SidebarLinks items={nav.main} />
                    </nav>

                    <div className="space-y-1 border-t border-white/[0.08] px-3 py-3">
                        <SidebarLinks items={nav.footer} />
                        <Link href="/logout" method="post" as="button" className={`${sideLinkClass(false)} w-full`}>
                            <LuLogOut className="h-[18px] w-[18px]" />
                            Log out
                        </Link>
                        {email && <p className="truncate px-3 pt-1 text-xs text-white/45">{email}</p>}
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
                    {/* Full width at every size - pages lay out their own columns inside. */}
                    <div className="w-full flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8 2xl:px-12 2xl:py-10">
                        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h1 className="font-heading text-2xl font-semibold text-brand-text sm:text-3xl">{title}</h1>
                                {description && <p className="mt-1 text-sm text-brand-muted">{description}</p>}
                            </div>
                            {actions}
                        </div>

                        <StatusBanner />

                        {children}
                    </div>

                    <TechsaFooter theme="brand" className="pb-4 pt-8" />
                </main>

                {/* Mobile bottom tabs - same soft navy as the desktop sidebar, reaching down under the
                    home indicator. Scroll sideways on the narrowest phones rather than squashing 7 labels. */}
                <nav className="no-scrollbar fixed inset-x-0 bottom-0 z-20 flex overflow-x-auto bg-brand-deep-soft pb-[env(safe-area-inset-bottom)] text-white lg:hidden">
                    <TabLinks items={nav.main} scroll />
                </nav>
            </div>
        </>
    );
}
