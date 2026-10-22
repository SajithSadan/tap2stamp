import { LuCheck } from "react-icons/lu";

/**
 * A plan's feature line, on the landing page and the owner's Plan page:
 * "Everything in Growth, plus:" is a small heading; "Title (detail)" shows the
 * detail smaller.
 */
export default function PlanFeature({ text, dark = false, compact = false }) {
    if (text.trim().endsWith(":")) {
        return <li className={`pt-1 text-xs font-semibold uppercase tracking-wide ${dark ? "text-brand-accent" : "text-brand-muted"}`}>{text}</li>;
    }

    const match = text.match(/^(.*?)\s*\((.+)\)\s*$/);

    return (
        <li className={`flex items-start ${compact ? "gap-2" : "gap-2.5"}`}>
            <LuCheck className={`${compact ? "mt-0.5" : "mt-1"} h-4 w-4 shrink-0 text-brand-accent`} strokeWidth={3} />
            <span>
                {match ? match[1] : text}
                {match && (
                    <span className={`mt-0.5 block leading-snug ${compact ? "text-xs" : "text-[13px]"} ${dark ? "text-white/60" : "text-brand-muted"}`}>{match[2]}</span>
                )}
            </span>
        </li>
    );
}
