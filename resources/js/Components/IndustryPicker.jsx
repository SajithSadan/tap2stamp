import { industryIcon } from "@/lib/industryIcons";

/** Options grouped in the server's order: [{group, label, items}]. */
function grouped(industries) {
    const groups = [];
    for (const industry of industries) {
        let group = groups.find((g) => g.group === industry.group);
        if (!group) {
            group = { group: industry.group, label: industry.group_label, items: [] };
            groups.push(group);
        }
        group.items.push(industry);
    }
    return groups;
}

/**
 * "What kind of business is it?" (App\Support\Industries). `tiles` = one-tap
 * icon tiles (sign-up, the dashboard popup); `select` = a dropdown (Settings,
 * admin forms). "Something else" asks for the owner's own words.
 */
export default function IndustryPicker({
    id = "industry",
    industries,
    value,
    other,
    onChange,
    onOtherChange,
    variant = "tiles",
    inputClassName,
    otherError,
    allowEmpty = false,
}) {
    const otherInput = value === "other" && (
        <div className="mt-3">
            <label htmlFor={`${id}_other`} className="sr-only">
                What kind of business?
            </label>
            <input
                id={`${id}_other`}
                value={other ?? ""}
                onChange={(e) => onOtherChange(e.target.value)}
                maxLength={80}
                placeholder="e.g. Bookshop, Opticians, Hotel"
                aria-invalid={otherError ? true : undefined}
                className={inputClassName}
                autoFocus={variant === "tiles"}
            />
            {otherError && <p className="mt-1.5 text-sm text-red-600">{otherError}</p>}
        </div>
    );

    if (variant === "select") {
        return (
            <div>
                <select id={id} value={value ?? ""} onChange={(e) => onChange(e.target.value || null)} className={inputClassName}>
                    <option value="" disabled={!allowEmpty}>
                        {allowEmpty ? "Not set" : "Choose…"}
                    </option>
                    {grouped(industries).map((g) => (
                        <optgroup key={g.group} label={g.label}>
                            {g.items.map((industry) => (
                                <option key={industry.key} value={industry.key}>
                                    {industry.label}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                </select>
                {otherInput}
            </div>
        );
    }

    return (
        <div id={id}>
            <div className="space-y-4">
                {grouped(industries).map((g) => (
                    <fieldset key={g.group}>
                        <legend className="mb-2 text-xs font-semibold uppercase tracking-wider text-brand-muted">{g.label}</legend>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {g.items.map((industry) => {
                                const Icon = industryIcon(industry.key);
                                const selected = value === industry.key;
                                return (
                                    <button
                                        key={industry.key}
                                        type="button"
                                        onClick={() => onChange(industry.key)}
                                        aria-pressed={selected}
                                        className={`flex min-h-12 items-center gap-2.5 rounded-xl border px-3 py-2 text-left text-sm transition-colors ${
                                            selected
                                                ? "border-brand-accent bg-brand-accent/10 font-semibold text-brand-text ring-1 ring-brand-accent"
                                                : "border-brand-border bg-brand-card text-brand-text hover:border-brand-accent/60"
                                        }`}
                                    >
                                        <Icon className={`h-4.5 w-4.5 shrink-0 ${selected ? "text-brand-accent" : "text-brand-muted"}`} />
                                        <span className="leading-snug">{industry.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ))}
            </div>
            {otherInput}
        </div>
    );
}
