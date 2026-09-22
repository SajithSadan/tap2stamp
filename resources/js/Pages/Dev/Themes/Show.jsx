import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ThemeCustomizer from '@/Components/ThemeCustomizer';

export default function ThemeShow({
    slug,
    theme,
    isCustom = false,
    customThemeData = null,
    availableFonts = [],
    radiusPresets = [],
}) {
    const [wifiOpen, setWifiOpen] = useState(false);
    const [customizing, setCustomizing] = useState(false);
    // The mockup always renders from `draft`, seeded from the server-provided
    // theme and live-updated by ThemeCustomizer while editing — one source
    // of truth whether or not the customizer panel is open.
    const [draft, setDraft] = useState(theme);

    const qrCells = useMemo(
        () => Array.from({ length: 25 }, () => Math.random() > 0.45),
        []
    );

    // The Google Fonts CSS2 API takes one `family=` param per font family —
    // a `|`-joined single value returns a 400 (text/html), which Chrome then
    // blocks reading cross-origin as CORB. `google_fonts` stores families
    // joined by `|`, so split them back out into separate params here.
    const fontsHref = useMemo(() => {
        const families = draft.google_fonts.split('|').map((f) => `family=${f}`);
        return `https://fonts.googleapis.com/css2?${families.join('&')}&display=swap`;
    }, [draft.google_fonts]);

    return (
        <>
            <Head title={`${theme.name} — Theme Preview`}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href={fontsHref} rel="stylesheet" />
            </Head>

            <div
                className="min-h-screen antialiased"
                style={{ background: draft.page_bg, color: draft.text, fontFamily: draft.body_font }}
            >
                <div className="mx-auto max-w-sm px-5 py-8">
                    <div className="flex items-center justify-between">
                        <Link href="/dev/themes" className="text-xs opacity-70 hover:opacity-100">
                            &larr; back to theme picker
                        </Link>
                        {availableFonts.length > 0 && (
                            <button
                                onClick={() => setCustomizing((open) => !open)}
                                className="rounded-full border px-3 py-1 text-xs font-medium"
                                style={{ borderColor: draft.border, color: draft.text }}
                            >
                                {customizing ? 'Close editor' : 'Customize this theme'}
                            </button>
                        )}
                    </div>

                    <div className="mt-4 flex items-center justify-between">
                        <div>
                            <p className="text-xs uppercase tracking-wide opacity-60">{draft.name}</p>
                            <h1 className="text-xl font-bold" style={{ fontFamily: draft.heading_font }}>
                                Artisan Cafe
                            </h1>
                        </div>
                    </div>

                    {customizing && (
                        <ThemeCustomizer
                            theme={theme}
                            isCustom={isCustom}
                            slug={slug}
                            customThemeData={customThemeData}
                            availableFonts={availableFonts}
                            radiusPresets={radiusPresets}
                            onDraftChange={(partial) => setDraft((prev) => ({ ...prev, ...partial }))}
                        />
                    )}

                    {/* Loyalty card */}
                    <div
                        className="mt-5 p-5"
                        style={{
                            background: draft.card_bg,
                            borderRadius: draft.radius,
                            boxShadow: draft.shadow,
                            border: `1px solid ${draft.border}`,
                        }}
                    >
                        <p className="text-lg font-semibold" style={{ fontFamily: draft.heading_font }}>
                            Free coffee, every 6th visit
                        </p>
                        <p className="mt-1 text-sm" style={{ color: draft.muted }}>
                            3 / 6 &middot; free coffee at 6
                        </p>

                        <div className="mt-4 grid grid-cols-6 gap-2">
                            {Array.from({ length: 6 }, (_, i) => i + 1).map((i) => (
                                <div
                                    key={i}
                                    className="flex aspect-square items-center justify-center rounded-full text-xs font-semibold"
                                    style={{
                                        background: i <= 3 ? draft.stamp_filled : draft.stamp_empty,
                                        color: i <= 3 ? draft.accent_text : draft.muted,
                                    }}
                                >
                                    {i <= 3 && '✓'}
                                </div>
                            ))}
                        </div>

                        <p className="mt-3 text-xs" style={{ color: draft.muted }}>
                            Rewards claimed: 2
                        </p>

                        <div className="mt-4 flex justify-center">
                            <div
                                className="grid h-32 w-32 grid-cols-5 grid-rows-5 gap-0.5 p-2"
                                style={{ background: '#FFFFFF', borderRadius: '12px', border: `1px solid ${draft.border}` }}
                            >
                                {qrCells.map((filled, i) => (
                                    <div key={i} style={{ background: filled ? '#111111' : 'transparent' }} />
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Hub tiles */}
                    <div className="mt-4 space-y-3">
                        <a
                            href="#"
                            onClick={(e) => e.preventDefault()}
                            className="flex items-center justify-between px-4 py-3 text-sm font-medium"
                            style={{ background: draft.card_bg, borderRadius: draft.button_radius, border: `1px solid ${draft.border}` }}
                        >
                            Leave a Google Review
                            <span style={{ color: draft.accent }}>&rarr;</span>
                        </a>
                        <a
                            href="#"
                            onClick={(e) => e.preventDefault()}
                            className="flex items-center justify-between px-4 py-3 text-sm font-medium"
                            style={{ background: draft.card_bg, borderRadius: draft.button_radius, border: `1px solid ${draft.border}` }}
                        >
                            Follow us on Instagram
                            <span style={{ color: draft.accent }}>&rarr;</span>
                        </a>

                        <div style={{ background: draft.card_bg, borderRadius: draft.button_radius, border: `1px solid ${draft.border}` }}>
                            <button
                                onClick={() => setWifiOpen((open) => !open)}
                                className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                            >
                                Free Wi-Fi
                                <span style={{ color: draft.accent }}>{wifiOpen ? '−' : '+'}</span>
                            </button>
                            {wifiOpen && (
                                <div className="px-4 pb-4 text-sm" style={{ color: draft.muted }}>
                                    <p>
                                        SSID: <span style={{ color: draft.text }}>ArtisanCafe-Guest</span>
                                    </p>
                                    <p className="mt-1">
                                        Password: <span style={{ color: draft.text }}>latte1234</span>
                                    </p>
                                    <button
                                        className="mt-3 px-3 py-1.5 text-xs font-semibold"
                                        style={{ background: draft.accent, color: draft.accent_text, borderRadius: draft.button_radius }}
                                    >
                                        Copy password
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    <button
                        className="mt-5 w-full px-4 py-3 text-sm font-semibold"
                        style={{ background: draft.accent, color: draft.accent_text, borderRadius: draft.button_radius }}
                    >
                        Register this card
                    </button>
                </div>
            </div>
        </>
    );
}
