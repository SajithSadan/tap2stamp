import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function ThemesIndex({ themes }) {
    const [search, setSearch] = useState('');

    const entries = Object.entries(themes);
    const needle = search.toLowerCase();
    const visible = entries.filter(
        ([, theme]) =>
            needle === '' || `${theme.name} ${theme.blurb}`.toLowerCase().includes(needle)
    );

    return (
        <>
            <Head title="Theme Preview" />
            <main className="mx-auto max-w-2xl px-6 py-12">
                <h1 className="text-2xl font-semibold">Pick a theme</h1>
                <p className="mt-2 text-sm text-stone-500">
                    {entries.length} candidate look-and-feel combos for the customer loyalty
                    card, each rendered with real dummy data. Open each one, then tell me which
                    fits best.
                </p>

                <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                    <input
                        type="text"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder='Search themes (e.g. "pub", "pastel", "dark")…'
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

                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    {visible.map(([slug, theme]) => (
                        <Link
                            key={slug}
                            href={`/dev/themes/${slug}`}
                            className="block rounded-xl border border-stone-200 p-5 transition hover:border-stone-400 hover:shadow-md"
                        >
                            <div className="flex gap-2">
                                <span
                                    className="h-8 w-8 rounded-full"
                                    style={{ background: theme.page_bg, border: `1px solid ${theme.border}` }}
                                />
                                <span className="h-8 w-8 rounded-full" style={{ background: theme.accent }} />
                                <span className="h-8 w-8 rounded-full" style={{ background: theme.stamp_filled }} />
                            </div>
                            <h2 className="mt-3 font-semibold">{theme.name}</h2>
                            <p className="mt-1 text-sm text-stone-500">{theme.blurb}</p>
                        </Link>
                    ))}
                </div>

                {visible.length === 0 && (
                    <p className="mt-8 text-center text-sm text-stone-400">
                        No themes match "{search}".
                    </p>
                )}
            </main>
        </>
    );
}
