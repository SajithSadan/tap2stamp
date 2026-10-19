import { FaWhatsapp } from "react-icons/fa6";
import { LuBuilding2, LuMail, LuMapPin, LuPhone } from "react-icons/lu";
import PublicShell from "@/Components/PublicShell";

/** /contact: who runs Tada Tap and how to reach us (Admin → Settings → Legal). */
export default function Contact({ company, companyNumber, address, email, phones = [] }) {
    const rows = [
        { icon: LuBuilding2, label: "Company", value: [company, companyNumber && `Company number ${companyNumber}`].filter(Boolean).join(" · ") },
        address && { icon: LuMapPin, label: "Address", value: address },
        email && { icon: LuMail, label: "Email", value: email, href: `mailto:${email}` },
        // UK, India...: call, or WhatsApp the same number.
        ...phones.map((p) => ({ icon: LuPhone, label: p.label ? `${p.label} phone` : "Phone", value: p.number, href: `tel:${p.tel}`, whatsapp: p.whatsapp })),
    ].filter(Boolean);

    return (
        <PublicShell title="Contact">
            <h1 className="font-heading text-3xl font-bold text-brand-deep sm:text-4xl">Contact us</h1>
            <p className="mt-3 text-brand-muted">
                Questions about your account, billing, a refund or an order? Email, call or WhatsApp us - we reply within 2 working days.
            </p>
            <dl className="mt-8 divide-y divide-brand-border rounded-2xl border border-brand-border">
                {rows.map((row) => (
                    <div key={row.label} className="flex items-start gap-4 p-5">
                        <row.icon className="mt-0.5 h-5 w-5 shrink-0 text-brand-accent" />
                        <div className="min-w-0">
                            <dt className="text-xs font-semibold uppercase tracking-wide text-brand-muted">{row.label}</dt>
                            <dd className="mt-0.5 whitespace-pre-line break-words text-brand-text">
                                {row.href ? (
                                    <a href={row.href} className="font-medium text-brand-accent hover:underline">
                                        {row.value}
                                    </a>
                                ) : (
                                    row.value
                                )}
                                {row.whatsapp && (
                                    <a
                                        href={row.whatsapp}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="ml-3 inline-flex items-center gap-1 rounded-full bg-[#25D366]/10 px-2.5 py-0.5 align-middle text-xs font-semibold text-[#128C4B] hover:bg-[#25D366]/20"
                                    >
                                        <FaWhatsapp className="h-3.5 w-3.5" /> WhatsApp
                                    </a>
                                )}
                            </dd>
                        </div>
                    </div>
                ))}
            </dl>
        </PublicShell>
    );
}
