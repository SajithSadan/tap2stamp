/**
 * The admin's follow-up status on a shop. Keys ↔ App\Support\LeadStatus::ALL - keep in sync.
 * Light colours: `pill` for the dropdown / chips, `dot` for small markers, `edge` for a note's left line,
 * `row` for the Shops grid row (opaque, with its hover).
 */
export const LEAD_STATUSES = [
    { key: "hot", label: "Hot", pill: "bg-rose-100 text-rose-800 border-rose-200", dot: "bg-rose-400", edge: "border-rose-300", row: "bg-rose-50 group-hover/row:bg-rose-100" },
    { key: "warm", label: "Warm", pill: "bg-amber-100 text-amber-800 border-amber-200", dot: "bg-amber-400", edge: "border-amber-300", row: "bg-amber-50 group-hover/row:bg-amber-100" },
    { key: "cold", label: "Cold", pill: "bg-sky-100 text-sky-800 border-sky-200", dot: "bg-sky-400", edge: "border-sky-300", row: "bg-sky-50 group-hover/row:bg-sky-100" },
];

export const leadStatus = (key) => LEAD_STATUSES.find((s) => s.key === key) ?? null;
