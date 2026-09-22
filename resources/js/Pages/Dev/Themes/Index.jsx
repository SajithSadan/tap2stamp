import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ThemeCard from '@/Components/ThemeCard';

const CATEGORIES = [
    { key: 'cafe', label: 'Cafe & Coffee' },
    { key: 'bakery', label: 'Bakery' },
    { key: 'barber', label: 'Barber & Grooming' },
    { key: 'pub', label: 'Pub & Bar' },
    { key: 'dessert', label: 'Dessert & Sweets' },
    { key: 'retail', label: 'Retail & Market' },
    { key: 'general', label: 'General / Any Shop' },
];

const CATEGORY_LABEL = Object.fromEntries(CATEGORIES.map((c) => [c.key, c.label]));

const MOODS = [
    { key: 'all', label: 'All' },
    { key: 'light', label: 'Light' },
    { key: 'dark', label: 'Dark' },
];

const STORAGE_KEY = 'loyalty-hub:dev-theme-filters';
const DEFAULT_FILTERS = { search: '', categories: [], mood: 'all' };

function loadStoredFilters() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return DEFAULT_FILTERS;
        const parsed = JSON.parse(raw);
        return {
            search: typeof parsed.search === 'string' ? parsed.search : '',
            categories: Array.isArray(parsed.categories) ? parsed.categories : [],
            mood: typeof parsed.mood === 'string' ? parsed.mood : 'all',
        };
    } catch {
        return DEFAULT_FILTERS;
    }
}

function persistFilters(filters) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
    } catch {
        // Private browsing / storage disabled — filters just won't persist.
    }
}

export default function ThemesIndex({ themes }) {
    const [filters, setFilters] = useState(loadStoredFilters);
    const { search, categories, mood } = filters;

    function updateFilters(updater) {
        setFilters((prev) => {
            const next = typeof updater === 'function' ? updater(prev) : { ...prev, ...updater };
            persistFilters(next);
            return next;
        });
    }

    function setSearch(value) {
        updateFilters((prev) => ({ ...prev, search: value }));
    }

    function toggleCategory(key) {
        updateFilters((prev) => ({
            ...prev,
            categories: prev.categories.includes(key)
                ? prev.categories.filter((c) => c !== key)
                : [...prev.categories, key],
        }));
    }

    function setMood(value) {
        updateFilters((prev) => ({ ...prev, mood: value }));
    }

    function clearFilters() {
        setFilters(DEFAULT_FILTERS);
        try {
            window.localStorage.removeItem(STORAGE_KEY);
        } catch {
            // Nothing to clean up if storage was never available.
        }
    }

    const entries = useMemo(() => Object.entries(themes), [themes]);

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return entries.filter(([, theme]) => {
            if (categories.length > 0 && !categories.includes(theme.category)) return false;
            if (mood !== 'all' && theme.mood !== mood) return false;
            if (needle === '') return true;

            const haystack = [theme.name, theme.blurb, CATEGORY_LABEL[theme.category], theme.mood]
                .join(' ')
                .toLowerCase();

            return haystack.includes(needle);
        });
    }, [entries, search, categories, mood]);

    const filtersActive = search !== '' || categories.length > 0 || mood !== 'all';

    return (
        <>
            <Head title="Theme Preview" />
            <main className="mx-auto max-w-5xl px-4 pb-12 pt-6 sm:px-6 sm:pt-12">
                <h1 className="text-xl font-semibold sm:text-2xl">Pick a theme</h1>
                <p className="mt-1 text-sm text-stone-500">
                    {entries.length} look-and-feel combos for the loyalty card — search or filter to compare.
                </p>

                {/* Sticky filter bar */}
                <div className="sticky top-0 z-10 -mx-4 mt-6 border-b border-stone-200 bg-stone-50/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder='Search name, blurb, category or mood…'
                            className="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:border-stone-500 focus:outline-none sm:flex-1 sm:py-2"
                        />
                        <select
                            onChange={(event) => {
                                if (event.target.value) router.visit(event.target.value);
                            }}
                            defaultValue=""
                            className="w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-sm focus:border-stone-500 focus:outline-none sm:w-56 sm:py-2"
                        >
                            <option value="">Jump to a theme…</option>
                            {entries.map(([slug, theme]) => (
                                <option key={slug} value={`/dev/themes/${slug}`}>
                                    {theme.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Category chips — horizontally scrollable on narrow screens, no visible scrollbar */}
                    <div className="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
                        <button
                            onClick={() => updateFilters((prev) => ({ ...prev, categories: [] }))}
                            className={`shrink-0 rounded-full border px-3 py-2 text-xs font-medium transition sm:py-1.5 ${
                                categories.length === 0
                                    ? 'border-stone-900 bg-stone-900 text-white'
                                    : 'border-stone-300 bg-white text-stone-600 hover:border-stone-400'
                            }`}
                        >
                            All
                        </button>
                        {CATEGORIES.map((c) => (
                            <button
                                key={c.key}
                                onClick={() => toggleCategory(c.key)}
                                aria-pressed={categories.includes(c.key)}
                                className={`shrink-0 rounded-full border px-3 py-2 text-xs font-medium transition sm:py-1.5 ${
                                    categories.includes(c.key)
                                        ? 'border-stone-900 bg-stone-900 text-white'
                                        : 'border-stone-300 bg-white text-stone-600 hover:border-stone-400'
                                }`}
                            >
                                {c.label}
                            </button>
                        ))}
                    </div>

                    {/* Mood toggle + result count + clear */}
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="no-scrollbar flex gap-2 overflow-x-auto">
                            {MOODS.map((m) => (
                                <button
                                    key={m.key}
                                    onClick={() => setMood(m.key)}
                                    className={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition ${
                                        mood === m.key
                                            ? 'border-stone-900 bg-stone-100 text-stone-900'
                                            : 'border-stone-300 bg-white text-stone-500 hover:border-stone-400'
                                    }`}
                                >
                                    {m.label}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-3 text-xs text-stone-500">
                            <span>
                                Showing {visible.length} of {entries.length}
                            </span>
                            {filtersActive && (
                                <button
                                    onClick={clearFilters}
                                    className="font-medium text-stone-700 underline underline-offset-2 hover:text-stone-900"
                                >
                                    Clear filters
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Results grid */}
                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {visible.map(([slug, theme]) => (
                        <ThemeCard
                            key={slug}
                            slug={slug}
                            theme={theme}
                            categoryLabel={CATEGORY_LABEL[theme.category]}
                        />
                    ))}
                </div>

                {visible.length === 0 && (
                    <div className="mt-16 text-center">
                        <p className="text-sm text-stone-400">
                            No themes match{search ? ` "${search}"` : ' these filters'}.
                        </p>
                        <button
                            onClick={clearFilters}
                            className="mt-2 text-sm font-medium text-stone-700 underline underline-offset-2 hover:text-stone-900"
                        >
                            Clear filters
                        </button>
                    </div>
                )}
            </main>
        </>
    );
}
