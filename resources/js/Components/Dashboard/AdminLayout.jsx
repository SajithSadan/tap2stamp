import { Head, Link, usePage } from '@inertiajs/react';
import { LuLogOut } from 'react-icons/lu';
import { Wordmark } from '@/Components/AuthShell';
import { SidebarLinks, TabLinks, useNavigation } from '@/Components/Dashboard/NavMenu';
import { navSurface, sideLinkClass, StatusBanner } from '@/Components/Dashboard/Ui';
import TechsaFooter from '@/Components/TechsaFooter';

/**
 * Admin shell: same sidebar/top-bar layout as OwnerLayout so the two sides
 * of the app feel like one product, but always in the default site look
 * (admins have no shop theme). The menu comes from App\Support\Navigation.
 */
export default function AdminLayout({ title, description, actions, children }) {
    const { props } = usePage();
    const email = props.auth?.user?.email;
    const nav = useNavigation();

    return (
        <>
            <Head title={`${title} · Admin`} />

            <div className="flex min-h-dvh flex-col bg-brand-bg">
                {/* Desktop sidebar - soft navy, same surface as the mobile tab bar. */}
                <aside className={`fixed inset-y-0 left-0 z-20 hidden w-64 flex-col lg:flex ${navSurface}`}>
                    <div className="px-5 py-5">
                        <Wordmark className="text-2xl text-white" />
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
                <header className="sticky top-0 z-20 flex items-center justify-between gap-2 border-b border-brand-border bg-brand-card/95 px-4 py-3 backdrop-blur lg:hidden">
                    <Link href="/admin" className="flex min-w-0 items-baseline gap-2">
                        <Wordmark className="text-xl text-brand-text" />
                        <span className="text-xs font-medium text-brand-muted">Admin</span>
                    </Link>
                    <Link href="/logout" method="post" as="button" aria-label="Log out" className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg">
                        <LuLogOut className="h-5 w-5" />
                    </Link>
                </header>

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

                {/* Mobile bottom tabs - same soft navy as the desktop sidebar,
                    reaching down under the home indicator (safe-area padding). */}
                <nav className="no-scrollbar fixed inset-x-0 bottom-0 z-20 flex overflow-x-auto bg-brand-deep-soft pb-[env(safe-area-inset-bottom)] text-white lg:hidden">
                    <TabLinks items={nav.main} scroll={nav.main.length > 5} />
                </nav>
            </div>
        </>
    );
}
