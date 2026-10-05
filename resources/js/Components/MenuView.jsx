import { useEffect, useId, useMemo, useRef, useState } from "react";
import { LuSearch, LuUtensils, LuX } from "react-icons/lu";
import { PiStorefrontFill } from "react-icons/pi";
import { headerTextStyle, headerTintStyle } from "@/lib/headerStyle";
import { StampIcon } from "@/lib/stampIcons";
import { themeVars, useThemeFonts } from "@/lib/theme";

const GENERIC_STAMP_ICONS = ["check", "star", "heart", "sparkles", "gift"];

/** Long menus get a search box. */
const SEARCH_FROM_ITEMS = 12;

function Header({ shop, embedded }) {
    return (
        <header className="relative overflow-hidden bg-brand-deep">
            {shop.banner_url && (
                <>
                    <img src={shop.banner_url} alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-black" style={headerTintStyle(shop.header_style)} />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />
                </>
            )}
            <div className={`relative mx-auto flex max-w-xl items-center gap-3 px-4 ${embedded ? "pb-5 pt-9" : "pb-7 pt-14"}`}>
                <span className={`flex shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white ${embedded ? "h-11 w-11" : "h-14 w-14"}`}>
                    {shop.logo_url ? (
                        <img src={shop.logo_url} alt={`${shop.name} logo`} className="h-full w-full object-cover" />
                    ) : GENERIC_STAMP_ICONS.includes(shop.stamp_icon) ? (
                        <PiStorefrontFill className="h-1/2 w-1/2 text-brand-accent" aria-hidden="true" />
                    ) : (
                        <StampIcon icon={shop.stamp_icon} className="h-1/2 w-1/2 text-brand-accent" aria-hidden="true" />
                    )}
                </span>
                <div className="min-w-0" style={headerTextStyle(shop.header_style)}>
                    <h1 className={`line-clamp-2 font-heading font-semibold leading-tight ${embedded ? "text-lg" : "text-2xl"}`}>
                        {shop.name}
                    </h1>
                    <p className="mt-0.5 text-xs uppercase tracking-[0.2em] opacity-75">Menu</p>
                </div>
            </div>
        </header>
    );
}

function Tags({ tags }) {
    if (!tags?.length) return null;

    return (
        <p className="mt-1.5 flex flex-wrap gap-1">
            {tags.map((tag) => (
                <span
                    key={tag}
                    className="rounded-full border border-brand-border px-1.5 py-px text-[10px] font-semibold uppercase tracking-wide text-brand-muted"
                >
                    {tag}
                </span>
            ))}
        </p>
    );
}

/* ---------- The three layouts (MenuThemes::LAYOUTS) ---------- */

function ListSection({ section }) {
    return (
        <div className="rounded-brand border border-brand-border bg-brand-card">
            <h2 className="px-4 pb-1 pt-4 font-heading text-lg font-semibold text-brand-text">{section.name}</h2>
            <ul className="divide-y divide-brand-border">
                {section.items.map((item, i) => (
                    <li key={i} className="px-4 py-3">
                        <div className="flex items-baseline justify-between gap-4">
                            <p className="font-medium text-brand-text">{item.name}</p>
                            {item.price && <p className="shrink-0 font-semibold tabular-nums text-brand-text">{item.price}</p>}
                        </div>
                        {item.description && <p className="mt-0.5 text-sm text-brand-muted">{item.description}</p>}
                        <Tags tags={item.tags} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Printed-menu style: centred heading between rules, dotted leaders to the price. */
function ClassicSection({ section }) {
    return (
        <div className="py-2">
            <h2 className="flex items-center gap-3 font-heading text-xl font-semibold text-brand-text">
                <span className="h-px flex-1 bg-brand-border" />
                <span className="text-center">{section.name}</span>
                <span className="h-px flex-1 bg-brand-border" />
            </h2>
            <ul className="mt-4 space-y-4">
                {section.items.map((item, i) => (
                    <li key={i}>
                        <div className="flex items-baseline gap-2">
                            <p className="font-semibold text-brand-text">{item.name}</p>
                            <span className="min-w-4 flex-1 -translate-y-1 border-b border-dotted border-brand-muted/60" />
                            {item.price && <p className="shrink-0 font-semibold tabular-nums text-brand-accent">{item.price}</p>}
                        </div>
                        {item.description && <p className="mt-0.5 text-sm italic text-brand-muted">{item.description}</p>}
                        <Tags tags={item.tags} />
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Every item its own card, price in an accent pill. */
function CardsSection({ section }) {
    return (
        <div>
            <h2 className="mb-2 px-1 font-heading text-xl font-semibold text-brand-text">{section.name}</h2>
            <ul className="space-y-2">
                {section.items.map((item, i) => (
                    <li key={i} className="flex items-start justify-between gap-3 rounded-brand border border-brand-border bg-brand-card p-4">
                        <div className="min-w-0">
                            <p className="font-semibold text-brand-text">{item.name}</p>
                            {item.description && <p className="mt-0.5 text-sm text-brand-muted">{item.description}</p>}
                            <Tags tags={item.tags} />
                        </div>
                        {item.price && (
                            <span className="shrink-0 rounded-full bg-brand-accent px-2.5 py-1 text-xs font-bold tabular-nums text-brand-accent-text">
                                {item.price}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

const LAYOUTS = { list: ListSection, classic: ClassicSection, cards: CardsSection };

export const LAYOUT_LABELS = { list: "List", classic: "Classic", cards: "Cards" };

/**
 * A shop's menu in its menu theme (App\Support\MenuThemes). The public page
 * renders it full-size; `embedded` is the admin's live preview / theme picker
 * card: scoped theme, smaller header, no search or scroll tracking.
 */
export default function MenuView({ shop, sections, theme, embedded = false }) {
    useThemeFonts(theme);
    const idPrefix = useId().replace(/:/g, "");
    const navRef = useRef(null);
    const [query, setQuery] = useState("");
    const [active, setActive] = useState(0);
    const Section = LAYOUTS[theme.layout] ?? ListSection;

    const withItems = useMemo(() => sections.filter((s) => s.items.length > 0), [sections]);
    const itemCount = withItems.reduce((sum, s) => sum + s.items.length, 0);
    const search = query.trim().toLowerCase();
    const shown = search
        ? withItems
              .map((s) => ({
                  ...s,
                  items: s.items.filter((item) => `${item.name} ${item.description ?? ""}`.toLowerCase().includes(search)),
              }))
              .filter((s) => s.items.length > 0)
        : withItems;

    // Highlight the section being read, and keep its chip in view.
    useEffect(() => {
        if (embedded || search || withItems.length < 2) return;

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((e) => e.isIntersecting);
                if (visible.length) setActive(Number(visible[0].target.dataset.index));
            },
            { rootMargin: "-120px 0px -60% 0px" },
        );
        withItems.forEach((_, i) => {
            const el = document.getElementById(`${idPrefix}-section-${i}`);
            if (el) observer.observe(el);
        });

        return () => observer.disconnect();
    }, [embedded, search, withItems, idPrefix]);

    useEffect(() => {
        const nav = navRef.current;
        const chip = nav?.children[active];
        if (chip) nav.scrollTo({ left: chip.offsetLeft - nav.clientWidth / 2 + chip.clientWidth / 2, behavior: "smooth" });
    }, [active]);

    function jumpTo(index) {
        setActive(index);
        document.getElementById(`${idPrefix}-section-${index}`)?.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    const showSearch = !embedded && itemCount >= SEARCH_FROM_ITEMS;
    const showChips = !search && withItems.length > 1;

    return (
        <div
            style={embedded ? themeVars(theme) : undefined}
            className="min-h-full bg-brand-bg font-sans text-brand-text"
        >
            <Header shop={shop} embedded={embedded} />

            {(showSearch || showChips) && (
                <div className={`${embedded ? "" : "sticky top-0"} z-10 border-b border-brand-border bg-brand-bg`}>
                    <div className="mx-auto max-w-xl">
                        {showSearch && (
                            <div className="relative px-4 pt-3">
                                <LuSearch className="pointer-events-none absolute left-7 top-1/2 mt-1.5 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                                <input
                                    type="text"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search the menu"
                                    aria-label="Search the menu"
                                    className="w-full rounded-full border border-brand-border bg-brand-card py-2 pl-9 pr-9 text-sm text-brand-text outline-none placeholder:text-brand-muted focus:border-brand-accent"
                                />
                                {query && (
                                    <button
                                        type="button"
                                        onClick={() => setQuery("")}
                                        aria-label="Clear search"
                                        className="absolute right-6 top-1/2 mt-1.5 -translate-y-1/2 p-1 text-brand-muted"
                                    >
                                        <LuX className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        )}
                        {showChips && (
                            <nav
                                ref={navRef}
                                aria-label="Menu sections"
                                className="flex gap-2 overflow-x-auto px-4 py-2.5 [scrollbar-width:none]"
                            >
                                {withItems.map((section, index) => (
                                    <button
                                        key={index}
                                        type="button"
                                        tabIndex={embedded ? -1 : 0}
                                        onClick={() => !embedded && jumpTo(index)}
                                        className={`shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                                            index === active
                                                ? "bg-brand-accent text-brand-accent-text"
                                                : "border border-brand-border bg-brand-card text-brand-text"
                                        }`}
                                    >
                                        {section.name}
                                    </button>
                                ))}
                            </nav>
                        )}
                    </div>
                </div>
            )}

            <main className={`mx-auto max-w-xl space-y-5 px-4 ${embedded ? "py-4" : "pb-14 pt-5"}`}>
                {withItems.length === 0 && (
                    <div className="py-14 text-center">
                        <LuUtensils className="mx-auto h-8 w-8 text-brand-muted" />
                        <p className="mt-3 font-heading text-lg font-semibold text-brand-text">The menu is coming soon</p>
                        <p className="mt-1 text-sm text-brand-muted">Ask at the counter in the meantime.</p>
                    </div>
                )}

                {search && shown.length === 0 && (
                    <p className="py-10 text-center text-sm text-brand-muted">Nothing matches “{query.trim()}”.</p>
                )}

                {shown.map((section, index) => (
                    <section
                        key={`${section.name}-${index}`}
                        id={search ? undefined : `${idPrefix}-section-${index}`}
                        data-index={index}
                        className="scroll-mt-32"
                    >
                        <Section section={section} />
                    </section>
                ))}
            </main>
        </div>
    );
}
