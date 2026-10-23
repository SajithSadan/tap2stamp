import { useEffect, useRef, useState } from "react";
import { LuLoaderCircle } from "react-icons/lu";
import { LEAD_STATUSES, leadStatus } from "@/lib/leadStatus";

/**
 * Admin Shops grid → Follow-up column: the shop's status as a coloured pill (blank
 * until set). Double-click opens the dropdown; a pick is saved straight away
 * (PUT /admin/shops/{shop}/lead) and goes back to the old value if that fails.
 */
export default function LeadStatusCell({ shopId, shopName, value, onChange }) {
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const selectRef = useRef(null);
    const s = leadStatus(value);

    // Open the list right away where the browser allows it (Chrome / Edge).
    useEffect(() => {
        if (!editing || !selectRef.current) return;
        selectRef.current.focus();
        try {
            selectRef.current.showPicker?.();
        } catch {
            // Not allowed here - the focused select opens on the next click / Enter.
        }
    }, [editing]);

    async function pick(next) {
        setEditing(false);
        if (next === value) return;
        const before = value;
        onChange(next);
        setSaving(true);
        try {
            const { data } = await window.axios.put(`/admin/shops/${shopId}/lead`, { lead_status: next });
            onChange(data.lead_status);
        } catch {
            onChange(before);
        } finally {
            setSaving(false);
        }
    }

    if (editing) {
        return (
            <select
                ref={selectRef}
                value={value ?? ""}
                onChange={(e) => pick(e.target.value || null)}
                onBlur={() => setEditing(false)}
                onKeyDown={(e) => e.key === "Escape" && setEditing(false)}
                aria-label={`Follow-up status for ${shopName}`}
                className="h-7 w-24 rounded-full border border-brand-border bg-brand-card px-2 text-xs font-semibold text-brand-text outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40"
            >
                <option value="">-</option>
                {LEAD_STATUSES.map((o) => (
                    <option key={o.key} value={o.key}>
                        {o.label}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <div
            role="button"
            tabIndex={0}
            onDoubleClick={() => setEditing(true)}
            onKeyDown={(e) => e.key === "Enter" && setEditing(true)}
            title="Double-click to change"
            aria-label={`Follow-up status for ${shopName}: ${s?.label ?? "not set"}. Press Enter to change.`}
            className="flex h-7 w-24 cursor-pointer select-none items-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40"
        >
            {s && <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${s.pill}`}>{s.label}</span>}
            {saving && <LuLoaderCircle className="ml-1.5 h-3.5 w-3.5 animate-spin text-brand-muted" />}
        </div>
    );
}
