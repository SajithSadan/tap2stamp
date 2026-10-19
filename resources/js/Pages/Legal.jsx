import { Link } from "@inertiajs/react";
import PublicShell from "@/Components/PublicShell";

/**
 * /privacy, /terms, /refunds, /shipping (App\Support\LegalPages). `html` is
 * rendered on the server from Markdown with raw HTML stripped, so it's safe
 * to insert. Tabs to the other legal pages on top.
 */

// Readable long-form text without a typography plugin.
const PROSE = [
    "text-[15px] leading-7 text-brand-text",
    "[&_h2]:mt-10 [&_h2]:font-heading [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-brand-deep",
    "[&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-brand-deep",
    "[&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-6 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6",
    "[&_a]:font-medium [&_a]:text-brand-accent [&_a]:underline-offset-2 hover:[&_a]:underline [&_strong]:font-semibold",
    "[&_table]:mt-4 [&_table]:w-full [&_table]:border-collapse [&_table]:text-sm",
    "[&_th]:border-b [&_th]:border-brand-border [&_th]:bg-brand-bg [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold",
    "[&_td]:border-b [&_td]:border-brand-border [&_td]:px-3 [&_td]:py-2 [&_td]:align-top",
].join(" ");

export default function Legal({ page, title, html, updated, links = [] }) {
    return (
        <PublicShell title={title}>
            <nav aria-label="Legal pages" className="no-scrollbar -mx-1 mb-8 flex gap-1.5 overflow-x-auto px-1">
                {links.map((link) => (
                    <Link
                        key={link.slug}
                        href={link.href}
                        aria-current={link.slug === page ? "page" : undefined}
                        className={`shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold ${
                            link.slug === page ? "bg-brand-deep text-white" : "bg-brand-bg text-brand-muted hover:text-brand-text"
                        }`}
                    >
                        {link.title}
                    </Link>
                ))}
            </nav>
            <h1 className="font-heading text-3xl font-bold text-brand-deep sm:text-4xl">{title}</h1>
            <p className="mt-2 text-sm text-brand-muted">Last updated {updated}</p>
            <div className="mt-6 overflow-x-auto">
                <div className={PROSE} dangerouslySetInnerHTML={{ __html: html }} />
            </div>
        </PublicShell>
    );
}
