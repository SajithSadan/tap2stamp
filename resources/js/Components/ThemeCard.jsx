import { Link } from '@inertiajs/react';

const MOOD_ICON = { light: '☀', dark: '☽' };

export default function ThemeCard({ slug, theme, categoryLabel, href }) {
    return (
        <Link
            href={href ?? `/dev/themes/${slug}`}
            className="group block overflow-hidden rounded-xl border border-stone-200 bg-white transition hover:-translate-y-0.5 hover:border-stone-400 hover:shadow-lg"
        >
            <div className="flex h-16" style={{ background: theme.page_bg }}>
                <div
                    className="flex flex-1 items-center justify-center border-r"
                    style={{ background: theme.card_bg, borderColor: theme.border }}
                >
                    <span
                        className="h-6 w-6 rounded-full"
                        style={{ background: theme.stamp_filled, border: `1px solid ${theme.border}` }}
                    />
                </div>
                <div className="flex flex-1 items-center justify-center gap-1.5">
                    <span className="h-5 w-5 rounded-full" style={{ background: theme.accent }} />
                    <span className="h-5 w-5 rounded-full" style={{ background: theme.text, opacity: 0.85 }} />
                </div>
            </div>

            <div className="p-4">
                <div className="flex items-center gap-2 text-[11px] uppercase tracking-wide text-stone-400">
                    <span>{categoryLabel}</span>
                    <span aria-hidden="true">&middot;</span>
                    <span>
                        {MOOD_ICON[theme.mood]} {theme.mood}
                    </span>
                </div>
                <h2 className="mt-1 font-semibold text-stone-900">{theme.name}</h2>
                <p className="mt-1 text-sm leading-snug text-stone-500">{theme.blurb}</p>
            </div>
        </Link>
    );
}
