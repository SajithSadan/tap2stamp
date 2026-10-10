import { useEffect, useId, useMemo, useRef, useState } from "react";
import { LuChevronDown, LuSearch } from "react-icons/lu";

/**
 * Country picker for a shop's location (App\Support\Countries::options()),
 * searchable: type part of the name ("ind" → India, Indonesia) or a usual
 * short form (UK, USA, UAE, KSA). With nothing typed, the most used
 * countries come first. Keyboard: ↑ ↓, Enter, Esc. Same props as the old
 * native <select>: `className` styles the text box.
 */

/** Shown first when nothing is typed - our main markets. */
const POPULAR = ["GB", "IN", "AE", "SA", "QA", "KW", "OM", "BH"];

/** Short forms people type instead of the name. */
const ALIASES = { UK: "GB", GB: "GB", USA: "US", US: "US", UAE: "AE", KSA: "SA", NZ: "NZ" };

/** 🇬🇧 from "GB" (regional indicator letters). */
const flag = (code) => String.fromCodePoint(...[...code.toUpperCase()].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));

const fold = (text) => text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

export default function CountrySelect({ id = "country", value, onChange, countries, className = "" }) {
    const listId = useId();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [active, setActive] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);
    const selected = countries.find((c) => c.code === value);

    // Popular first (no query), else: exact short form, then name starts with, then contains.
    const options = useMemo(() => {
        const q = fold(query.trim());
        if (!q) {
            const popular = POPULAR.map((code) => countries.find((c) => c.code === code)).filter(Boolean);
            return [
                ...popular.map((c) => ({ ...c, group: "Popular" })),
                ...countries.filter((c) => !POPULAR.includes(c.code)).map((c) => ({ ...c, group: "All countries" })),
            ];
        }
        const alias = ALIASES[query.trim().toUpperCase()];
        const starts = countries.filter((c) => fold(c.name).startsWith(q) || c.code.toLowerCase() === q);
        const contains = countries.filter((c) => !starts.includes(c) && fold(c.name).includes(q));
        const byAlias = alias ? countries.filter((c) => c.code === alias && !starts.includes(c)) : [];

        return [...byAlias, ...starts, ...contains];
    }, [countries, query]);

    useEffect(() => setActive(0), [query]);

    // Keep the highlighted option in view while moving with the keyboard.
    useEffect(() => {
        if (open) listRef.current?.querySelector(`[data-index="${active}"]`)?.scrollIntoView({ block: "nearest" });
    }, [active, open]);

    function choose(country) {
        onChange(country.code);
        setQuery("");
        setOpen(false);
    }

    function onKeyDown(e) {
        if (e.key === "ArrowDown") {
            e.preventDefault();
            setOpen(true);
            setActive((i) => Math.min(options.length - 1, i + 1));
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setActive((i) => Math.max(0, i - 1));
        } else if (e.key === "Enter") {
            if (open && options[active]) {
                e.preventDefault();
                choose(options[active]);
            }
        } else if (e.key === "Escape") {
            setOpen(false);
            setQuery("");
        }
    }

    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-lg" aria-hidden="true">
                {open || !selected ? <LuSearch className="h-4 w-4 text-brand-muted" /> : flag(selected.code)}
            </span>
            <input
                ref={inputRef}
                id={id}
                type="text"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={open && options[active] ? `${listId}-${options[active].code}` : undefined}
                autoComplete="off"
                spellCheck={false}
                placeholder={selected ? selected.name : "Search countries"}
                value={open ? query : (selected?.name ?? "")}
                onFocus={(e) => {
                    setOpen(true);
                    e.target.select();
                }}
                // Still focused after picking (the list closed): a click reopens it, ready to type over.
                onClick={(e) => {
                    if (!open) {
                        setOpen(true);
                        e.target.select();
                    }
                }}
                onChange={(e) => {
                    // Typing into the closed box (it shows the chosen name) starts a fresh search.
                    const typed = e.target.value;
                    setQuery(!open && selected && typed.startsWith(selected.name) ? typed.slice(selected.name.length) : typed);
                    setOpen(true);
                }}
                onBlur={() => {
                    // A click on an option fires before blur closes the list (mousedown is prevented there).
                    setOpen(false);
                    setQuery("");
                }}
                onKeyDown={onKeyDown}
                className={`${className} pl-10! pr-9!`}
            />
            <LuChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" aria-hidden="true" />

            {open && (
                <ul
                    ref={listRef}
                    id={listId}
                    role="listbox"
                    className="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-xl border border-brand-border bg-brand-card py-1 shadow-lg"
                >
                    {options.length === 0 && <li className="px-3 py-2.5 text-sm text-brand-muted">No country matches "{query}"</li>}
                    {options.map((c, i) => (
                        <li key={`${c.group ?? ""}${c.code}`} role="presentation">
                            {c.group && c.group !== options[i - 1]?.group && (
                                <p className="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-brand-muted">{c.group}</p>
                            )}
                            <div
                                id={`${listId}-${c.code}`}
                                role="option"
                                aria-selected={c.code === value}
                                data-index={i}
                                onMouseDown={(e) => e.preventDefault()}
                                onMouseEnter={() => setActive(i)}
                                onClick={() => choose(c)}
                                className={`flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm ${
                                    i === active ? "bg-brand-accent/10 text-brand-text" : "text-brand-text"
                                } ${c.code === value ? "font-semibold" : ""}`}
                            >
                                <span className="text-base" aria-hidden="true">
                                    {flag(c.code)}
                                </span>
                                <span className="min-w-0 flex-1 truncate">{c.name}</span>
                                {c.code === value && <span className="text-xs text-brand-accent">Selected</span>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
