import { usePage } from "@inertiajs/react";

/**
 * Privacy · Terms · Refunds · Delivery · Contact (the shared `legalLinks`,
 * App\Support\LegalPages::links()). Plain links, so they also work from the
 * customer card and staff app. `only`: just these slugs.
 */
export default function LegalLinks({ className = "", linkClassName = "hover:underline", only = null }) {
    const links = (usePage().props.legalLinks ?? []).filter((l) => !only || only.includes(l.slug));
    if (links.length === 0) return null;

    return (
        <nav aria-label="Legal" className={`flex flex-wrap items-center justify-center gap-x-3 gap-y-1 ${className}`}>
            {links.map((link) => (
                <a key={link.slug} href={link.href} className={linkClassName}>
                    {link.title.replace(" & Cancellation Policy", "s").replace(" Policy", "").replace(" of Service", "")}
                </a>
            ))}
        </nav>
    );
}
