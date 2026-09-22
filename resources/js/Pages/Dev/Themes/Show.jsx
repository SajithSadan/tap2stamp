import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function ThemeShow({ theme }) {
    const [wifiOpen, setWifiOpen] = useState(false);

    const qrCells = useMemo(
        () => Array.from({ length: 25 }, () => Math.random() > 0.45),
        []
    );

    // The Google Fonts CSS2 API takes one `family=` param per font family —
    // a `|`-joined single value returns a 400 (text/html), which Chrome then
    // blocks reading cross-origin as CORB. `google_fonts` stores families
    // joined by `|`, so split them back out into separate params here.
    const fontsHref = useMemo(() => {
        const families = theme.google_fonts.split('|').map((f) => `family=${f}`);
        return `https://fonts.googleapis.com/css2?${families.join('&')}&display=swap`;
    }, [theme.google_fonts]);

    return (
        <>
            <Head title={`${theme.name} — Theme Preview`}>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link href={fontsHref} rel="stylesheet" />
            </Head>

            <div
                className="min-h-screen antialiased"
                style={{ background: theme.page_bg, color: theme.text, fontFamily: theme.body_font }}
            >
                <div className="mx-auto max-w-sm px-5 py-8">
                    <Link href="/dev/themes" className="text-xs opacity-70 hover:opacity-100">
                        &larr; back to theme picker
                    </Link>

                    <div className="mt-4 flex items-center justify-between">
                        <div>
                            <p className="text-xs uppercase tracking-wide opacity-60">{theme.name}</p>
                            <h1 className="text-xl font-bold" style={{ fontFamily: theme.heading_font }}>
                                Artisan Cafe
                            </h1>
                        </div>
                    </div>

                    {/* Loyalty card */}
                    <div
                        className="mt-5 p-5"
                        style={{
                            background: theme.card_bg,
                            borderRadius: theme.radius,
                            boxShadow: theme.shadow,
                            border: `1px solid ${theme.border}`,
                        }}
                    >
                        <p className="text-lg font-semibold" style={{ fontFamily: theme.heading_font }}>
                            Free coffee, every 6th visit
                        </p>
                        <p className="mt-1 text-sm" style={{ color: theme.muted }}>
                            3 / 6 &middot; free coffee at 6
                        </p>

                        <div className="mt-4 grid grid-cols-6 gap-2">
                            {Array.from({ length: 6 }, (_, i) => i + 1).map((i) => (
                                <div
                                    key={i}
                                    className="flex aspect-square items-center justify-center rounded-full text-xs font-semibold"
                                    style={{
                                        background: i <= 3 ? theme.stamp_filled : theme.stamp_empty,
                                        color: i <= 3 ? theme.accent_text : theme.muted,
                                    }}
                                >
                                    {i <= 3 && '✓'}
                                </div>
                            ))}
                        </div>

                        <p className="mt-3 text-xs" style={{ color: theme.muted }}>
                            Rewards claimed: 2
                        </p>

                        <div className="mt-4 flex justify-center">
                            <div
                                className="grid h-32 w-32 grid-cols-5 grid-rows-5 gap-0.5 p-2"
                                style={{ background: '#FFFFFF', borderRadius: '12px', border: `1px solid ${theme.border}` }}
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
                            style={{ background: theme.card_bg, borderRadius: theme.button_radius, border: `1px solid ${theme.border}` }}
                        >
                            Leave a Google Review
                            <span style={{ color: theme.accent }}>&rarr;</span>
                        </a>
                        <a
                            href="#"
                            onClick={(e) => e.preventDefault()}
                            className="flex items-center justify-between px-4 py-3 text-sm font-medium"
                            style={{ background: theme.card_bg, borderRadius: theme.button_radius, border: `1px solid ${theme.border}` }}
                        >
                            Follow us on Instagram
                            <span style={{ color: theme.accent }}>&rarr;</span>
                        </a>

                        <div style={{ background: theme.card_bg, borderRadius: theme.button_radius, border: `1px solid ${theme.border}` }}>
                            <button
                                onClick={() => setWifiOpen((open) => !open)}
                                className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium"
                            >
                                Free Wi-Fi
                                <span style={{ color: theme.accent }}>{wifiOpen ? '−' : '+'}</span>
                            </button>
                            {wifiOpen && (
                                <div className="px-4 pb-4 text-sm" style={{ color: theme.muted }}>
                                    <p>
                                        SSID: <span style={{ color: theme.text }}>ArtisanCafe-Guest</span>
                                    </p>
                                    <p className="mt-1">
                                        Password: <span style={{ color: theme.text }}>latte1234</span>
                                    </p>
                                    <button
                                        className="mt-3 px-3 py-1.5 text-xs font-semibold"
                                        style={{ background: theme.accent, color: theme.accent_text, borderRadius: theme.button_radius }}
                                    >
                                        Copy password
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    <button
                        className="mt-5 w-full px-4 py-3 text-sm font-semibold"
                        style={{ background: theme.accent, color: theme.accent_text, borderRadius: theme.button_radius }}
                    >
                        Register this card
                    </button>
                </div>
            </div>
        </>
    );
}
