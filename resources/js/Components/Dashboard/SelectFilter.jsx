import { LuChevronDown } from "react-icons/lu";

/**
 * A pill-shaped dropdown that narrows a grid (country, industry, …), with how
 * many rows each choice has. Dark while something is chosen. `value` "all" =
 * no filter.
 */
export default function SelectFilter({ label, icon: Icon, allLabel, value, onChange, options, total }) {
    return (
        <label className="relative flex min-w-0 items-center md:shrink-0">
            <span className="sr-only">{label}</span>
            <Icon className={`pointer-events-none absolute left-3 h-4 w-4 ${value === "all" ? "text-brand-muted" : "text-white/70"}`} />
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={`h-[42px] w-full min-w-0 appearance-none truncate rounded-xl md:w-auto md:max-w-56 pl-9 pr-8 text-sm font-medium ring-1 ring-inset transition-colors focus:outline-none focus:ring-2 focus:ring-brand-accent ${
                    value === "all"
                        ? "bg-brand-card text-brand-text ring-brand-border hover:bg-brand-bg"
                        : "bg-brand-deep text-white ring-brand-deep"
                }`}
            >
                <option value="all">
                    {allLabel}
                    {total !== undefined && ` (${total})`}
                </option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                        {o.count !== undefined && ` (${o.count})`}
                    </option>
                ))}
            </select>
            <LuChevronDown
                className={`pointer-events-none absolute right-3 h-3.5 w-3.5 ${value === "all" ? "text-brand-muted" : "text-white/70"}`}
            />
        </label>
    );
}
