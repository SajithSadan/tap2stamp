import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ThemeCard from '@/Components/ThemeCard';

const CATEGORIES = [
    { key: 'all', label: 'All' },
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

export default function ThemesIndex({ themes }) {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [mood, setMood] = useState('all');

    const entries = useMemo(() => Object.entries(themes), [themes]);

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return entries.filter(([, theme]) => {
            if (category !== 'all' && theme.category !== category) return false;
            if (mood !== 'all' && theme.mood !== mood) return false;
            if (needle === '') return true;

            const haystack = [
                theme.name,
                theme.blurb,
                CATEGORY_LABEL[theme.category],
                theme.mood,
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(needle);
        });
    }, [entries, search, category, mood]);

    const filtersActive = search !== '' || category !== 'all' || mood !== 'all';

    function clearFilters() {
        setSearch('');
        setCategory('all');
        setMood('all');
    }

    return (
        <>
            <Head title="Theme Preview" />
            <main className="mx-auto max-w-5xl px-6 py-12">
                <h1 className="text-2xl font-semibold">Pick a theme</h1>
                <p className="mt-2 max-w-2xl text-sm text-stone-500">
                    {entries.length} candidate look-and-feel combos for the customer loyalty
                    card, each rendered with real dummy data. This picker's layout is also the
                    starting point for a future "choose your shop's theme" feature — filtering
                    and search matter here, not just this one decision.
                </p>

                {/* Search + jump-to */}
                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <input
                        type="text"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder='Search name, blurb, category or mood (e.g. "pub", "dark", "bakery")…'
                        className="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none sm:flex-1"
                    />
                    <select
                        onChange={(event) => {
                            if (event.target.value) router.visit(event.target.value);
                        }}
                        defaultValue=""
                        className="w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm focus:border-stone-500 focus:outline-none sm:w-56"
                    >
                        <option value="">Jump to a theme…</option>
                        {entries.map(([slug, theme]) => (
                            <option key={slug} value={`/dev/themes/${slug}`}>
                                {theme.name}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Category chips */}
                <div className="mt-4 flex flex-wrap gap-2">
                    {CATEGORIES.map((c) => (
                        <button
                            key={c.key}
                            onClick={() => setCategory(c.key)}
                            className={`rounded-full border px-3 py-1.5 text-xs font-medium transition ${
                                category === c.key
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
                    <div className="flex gap-2">
                        {MOODS.map((m) => (
                            <button
                                key={m.key}
                                onClick={() => setMood(m.key)}
                                className={`rounded-full border px-3 py-1 text-xs font-medium transition ${
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
