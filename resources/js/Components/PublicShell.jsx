import { Head, Link } from "@inertiajs/react";
import { Wordmark } from "@/Components/AuthShell";
import LegalLinks from "@/Components/LegalLinks";
import SupportChatButton from "@/Components/SupportChatButton";
import CookieNotice from "@/Components/CookieNotice";

/** The plain public frame for the legal pages and Contact: logo header, content, legal footer. */
export default function PublicShell({ title, children }) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-dvh flex-col bg-white text-brand-text">
                <header className="border-b border-brand-border">
                    <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Link href="/" aria-label="Tada Tap home">
                            <Wordmark />
                        </Link>
                        <Link href="/login" className="text-sm font-medium text-brand-text hover:text-brand-accent">
                            Log in
                        </Link>
                    </div>
                </header>
                <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6 sm:py-14">{children}</main>
                <footer className="border-t border-brand-border">
                    <div className="mx-auto flex max-w-4xl flex-col items-center gap-2 px-4 py-6 text-sm text-brand-muted sm:px-6">
                        <LegalLinks linkClassName="hover:text-brand-text" />
                        <span>© {new Date().getFullYear()} Tada Tap</span>
                    </div>
                </footer>
            </div>
            <SupportChatButton />
            <CookieNotice />
        </>
    );
}
