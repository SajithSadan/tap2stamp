import { useEffect, useRef, useState } from "react";
import { LuChevronDown, LuLink, LuSearch, LuStore } from "react-icons/lu";
import { FieldError, inputClass } from "@/Components/Dashboard/Ui";

/**
 * Searchable shop dropdown. `shops`: [{id, name, slug, qr_codes_count?}].
 * Used to filter QR codes by shop, and to map a sticker to a shop's card.
 */
export function ShopPicker({
    shops,
    value,
    onChange,
    label,
    emptyLabel = "Not assigned to a shop",
    id,
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const rootRef = useRef(null);
    const selected = shops.find((shop) => shop.id === Number(value));
    const filtered = shops.filter((shop) =>
        shop.name.toLowerCase().includes(query.trim().toLowerCase()),
    );

    useEffect(() => {
        if (!open) return;

        function closeOnOutsideClick(event) {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
                setQuery("");
            }
        }

        function closeOnEscape(event) {
            if (event.key === "Escape") {
                setOpen(false);
                setQuery("");
            }
        }

        document.addEventListener("pointerdown", closeOnOutsideClick);
        document.addEventListener("keydown", closeOnEscape);
        return () => {
            document.removeEventListener("pointerdown", closeOnOutsideClick);
            document.removeEventListener("keydown", closeOnEscape);
        };
    }, [open]);

    function choose(shopId) {
        onChange(shopId);
        setOpen(false);
        setQuery("");
    }

    return (
        <div ref={rootRef} className="relative">
            <div className="relative">
                <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                <input
                    id={id}
                    type="text"
                    role="combobox"
                    aria-label={label}
                    aria-expanded={open}
                    aria-controls={`${id ?? "shop-picker"}-options`}
                    aria-autocomplete="list"
                    autoComplete="off"
                    value={open ? query : (selected?.name ?? "")}
                    onFocus={() => setOpen(true)}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    placeholder={selected?.name ?? label}
                    className={`${inputClass} w-full pl-9 pr-9`}
                />
                <LuChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
            </div>
            {open && (
                <ul
                    id={`${id ?? "shop-picker"}-options`}
                    role="listbox"
                    aria-label={label}
                    className="absolute inset-x-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-brand-border bg-brand-card p-1 shadow-xl"
                >
                    <li role="option" aria-selected={!value}>
                        <button
                            type="button"
                            onClick={() => choose("")}
                            className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition-colors ${!value ? "bg-brand-accent/10 font-medium text-brand-text" : "text-brand-muted hover:bg-brand-bg hover:text-brand-text"}`}
                        >
                            <span>{emptyLabel}</span>
                        </button>
                    </li>
                    {filtered.map((shop) => (
                        <li
                            key={shop.id}
                            role="option"
                            aria-selected={shop.id === Number(value)}
                        >
                            <button
                                type="button"
                                onClick={() => choose(shop.id)}
                                className={`flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors ${shop.id === Number(value) ? "bg-brand-accent/10 font-medium text-brand-text" : "text-brand-text hover:bg-brand-bg"}`}
                            >
                                <span className="min-w-0 truncate">
                                    {shop.name}
                                </span>
                                {shop.qr_codes_count !== undefined && (
                                    <span className="shrink-0 text-xs text-brand-muted">
                                        {shop.qr_codes_count} assigned
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                    {filtered.length === 0 && (
                        <li className="px-3 py-3 text-sm text-brand-muted">
                            No shops match “{query}”.
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}

/** The shop card link this app would map to (same origin, /s/{slug}). */
export function shopUrl(shop) {
    return `${window.location.origin}/s/${encodeURIComponent(shop.slug)}`;
}

/** Which shop a destination URL is the card page of, if any. */
export function shopForDestination(url, shops) {
    try {
        const destination = new URL(url);
        if (destination.origin !== window.location.origin) return null;

        const match = destination.pathname.match(/^\/s\/([^/]+)\/?$/);
        if (!match) return null;

        const slug = decodeURIComponent(match[1]);
        return shops.find((shop) => shop.slug === slug) ?? null;
    } catch {
        return null;
    }
}

/**
 * Where a QR sticker sends people: either one of our shops (picked from the
 * list - its card link is filled in for you) or any web address. Both end
 * up as `destination_url`; the server spots shop links and assigns the
 * sticker to that shop.
 */
export default function QrDestinationField({ value, onChange, shops, error, label = "Destination", autoFocus = false }) {
    const matched = shopForDestination(value, shops);
    const [mode, setMode] = useState(() => (value && !matched ? "url" : "shop"));

    const tab = (key, Icon, text) => (
        <button
            type="button"
            role="tab"
            aria-selected={mode === key}
            onClick={() => setMode(key)}
            className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                mode === key ? "bg-brand-card text-brand-text shadow-sm" : "text-brand-muted hover:text-brand-text"
            }`}
        >
            <Icon className="h-4 w-4" /> {text}
        </button>
    );

    return (
        <div>
            <p className="mb-1.5 text-sm font-medium text-brand-text">{label}</p>
            <div role="tablist" aria-label="Destination type" className="flex gap-1 rounded-xl bg-brand-bg p-1 ring-1 ring-inset ring-brand-border">
                {tab("shop", LuStore, "A shop")}
                {tab("url", LuLink, "Web address")}
            </div>

            <div className="mt-3">
                {mode === "shop" ? (
                    <>
                        <ShopPicker
                            id="destination-shop"
                            shops={shops}
                            value={matched?.id ?? ""}
                            onChange={(shopId) => {
                                const shop = shops.find((s) => s.id === Number(shopId));
                                onChange(shop ? shopUrl(shop) : "");
                            }}
                            label="Choose a shop"
                            emptyLabel="No shop"
                        />
                        {matched ? (
                            <p className="mt-1.5 truncate text-xs text-brand-muted" title={value}>
                                Opens <span className="font-mono">{value}</span> - their loyalty card.
                            </p>
                        ) : (
                            <p className="mt-1.5 text-xs text-brand-muted">Scanning it opens the shop's loyalty card, and the sticker is assigned to the shop.</p>
                        )}
                    </>
                ) : (
                    <>
                        <input
                            id="destination_url"
                            type="url"
                            inputMode="url"
                            value={value}
                            onChange={(e) => onChange(e.target.value)}
                            placeholder="https://example.com/event/summer-festival"
                            autoFocus={autoFocus}
                            className={inputClass}
                        />
                        {!error && (
                            <p className="mt-1.5 text-xs text-brand-muted">
                                {matched ? (
                                    <>
                                        That's <strong className="font-semibold text-brand-text">{matched.name}</strong>'s card - the sticker is assigned to them.
                                    </>
                                ) : (
                                    "Any http(s) address. Leave empty to unmap."
                                )}
                            </p>
                        )}
                    </>
                )}
                <FieldError message={error} />
            </div>
        </div>
    );
}
