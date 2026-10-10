import { Head } from "@inertiajs/react";
import axios from "axios";
import { motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import {
    LuCheck,
    LuChartColumn,
    LuDelete,
    LuGift,
    LuLogOut,
    LuScanLine,
    LuSearch,
    LuStamp,
    LuStore,
    LuUsers,
} from "react-icons/lu";
import CardScanner from "@/Components/CardScanner";
import OfflineBanner from "@/Components/OfflineBanner";
import TechsaFooter from "@/Components/TechsaFooter";
import { STAFF_TOKEN_KEY } from "@/lib/storage";


function CenteredMessage({ icon: Icon, title, children }) {
    return (
        <div className="flex min-h-dvh flex-col bg-brand-bg px-5">
            <div className="flex flex-1 items-center justify-center text-center">
                <div className="max-w-xs">
                    {Icon && (
                        <Icon className="mx-auto h-10 w-10 text-brand-muted" />
                    )}
                    <h1 className="mt-3 font-heading text-lg font-bold text-brand-text">
                        {title}
                    </h1>
                    {children}
                </div>
            </div>
            <TechsaFooter theme="light" className="pb-4" />
        </div>
    );
}

/* ---------- PIN sign-in ---------- */

function PinSignIn({ shopName, members, api, onSignedIn }) {
    const [member, setMember] = useState(null);
    const [pin, setPin] = useState("");
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [shake, setShake] = useState(0);

    function press(digit) {
        setError(null);
        setPin((p) => (p.length < 6 ? p + digit : p));
    }

    function submit() {
        if (pin.length < 4 || busy) return;
        setBusy(true);

        api.post("/api/staff/sign-in", { staff_member_id: member.id, pin })
            .then(({ data }) => onSignedIn(data.staff))
            .catch((err) => {
                const status = err.response?.status;
                setError(
                    status === 429
                        ? "Too many tries. Wait a minute and try again."
                        : status === 422
                          ? "That PIN doesn't match. Try again."
                          : "Could not reach the server. Check the connection.",
                );
                setPin("");
                setShake((n) => n + 1);
            })
            .finally(() => setBusy(false));
    }

    if (!member) {
        return (
            <div className="flex min-h-dvh flex-col bg-brand-bg px-5 pt-[max(2.5rem,env(safe-area-inset-top))]">
                <div className="mx-auto w-full max-w-sm flex-1">
                    <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-accent/10 text-brand-accent">
                        <LuStore className="h-7 w-7" />
                    </span>
                    <p className="mt-4 text-sm text-brand-muted">{shopName}</p>
                    <h1 className="font-heading text-2xl font-semibold text-brand-text">
                        Who's working?
                    </h1>

                    {members.length === 0 ? (
                        <p className="mt-6 rounded-2xl border border-brand-border bg-brand-card p-4 text-sm text-brand-muted">
                            No staff accounts yet. Ask the owner to add you in{" "}
                            <span className="text-brand-text">
                                Dashboard → Staff
                            </span>
                            .
                        </p>
                    ) : (
                        <div className="mt-6 grid grid-cols-2 gap-3">
                            {members.map((m) => (
                                <button
                                    key={m.id}
                                    type="button"
                                    onClick={() => setMember(m)}
                                    className="flex flex-col items-center gap-2 rounded-2xl border border-brand-border bg-brand-card px-3 py-5 shadow-sm transition active:scale-[0.98]"
                                >
                                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-brand-accent/10 text-lg font-semibold text-brand-accent">
                                        {m.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span className="w-full truncate text-sm font-medium text-brand-text">
                                        {m.name}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <TechsaFooter theme="light" className="pb-4" />
            </div>
        );
    }

    const keys = [
        "1",
        "2",
        "3",
        "4",
        "5",
        "6",
        "7",
        "8",
        "9",
        "back",
        "0",
        "enter",
    ];

    return (
        <div className="flex min-h-dvh flex-col bg-brand-bg px-5 pt-[max(2.5rem,env(safe-area-inset-top))]">
            <div className="mx-auto flex w-full max-w-xs flex-1 flex-col items-center">
                <button
                    type="button"
                    onClick={() => {
                        setMember(null);
                        setPin("");
                        setError(null);
                    }}
                    className="self-start text-sm font-medium text-brand-accent"
                >
                    ← Not {member.name}?
                </button>

                <span className="mt-6 flex h-16 w-16 items-center justify-center rounded-full bg-brand-accent/10 text-2xl font-semibold text-brand-accent">
                    {member.name.charAt(0).toUpperCase()}
                </span>
                <h1 className="mt-3 font-heading text-xl font-semibold text-brand-text">
                    Hi, {member.name}
                </h1>
                <p className="text-sm text-brand-muted">Enter your PIN</p>

                <motion.div
                    key={shake}
                    animate={shake > 0 ? { x: [0, -10, 10, -6, 6, 0] } : {}}
                    transition={{ duration: 0.4 }}
                    className="mt-5 flex h-4 items-center gap-3"
                    aria-live="polite"
                    aria-label={`${pin.length} digits entered`}
                >
                    {Array.from({ length: Math.max(4, pin.length) }, (_, i) => (
                        <span
                            key={i}
                            className={`h-3.5 w-3.5 rounded-full transition-colors ${i < pin.length ? "bg-brand-accent" : "border-2 border-brand-border"}`}
                        />
                    ))}
                </motion.div>
                <p
                    className="mt-3 h-5 text-center text-sm text-red-600"
                    role="alert"
                >
                    {error}
                </p>

                <div className="mt-4 grid w-full grid-cols-3 gap-3">
                    {keys.map((key) => {
                        if (key === "back") {
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() =>
                                        setPin((p) => p.slice(0, -1))
                                    }
                                    aria-label="Delete last digit"
                                    className="flex h-16 items-center justify-center rounded-2xl text-brand-muted active:bg-brand-border/50"
                                >
                                    <LuDelete className="h-6 w-6" />
                                </button>
                            );
                        }

                        if (key === "enter") {
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={submit}
                                    disabled={pin.length < 4 || busy}
                                    aria-label="Sign in"
                                    className="flex h-16 items-center justify-center rounded-2xl bg-brand-accent text-brand-accent-text shadow-sm transition-opacity disabled:opacity-40"
                                >
                                    {busy ? (
                                        <span className="h-5 w-5 animate-spin rounded-full border-2 border-brand-accent-text/40 border-t-brand-accent-text" />
                                    ) : (
                                        <LuCheck className="h-6 w-6" />
                                    )}
                                </button>
                            );
                        }

                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => press(key)}
                                className="h-16 rounded-2xl border border-brand-border bg-brand-card text-2xl font-semibold text-brand-text shadow-sm active:bg-brand-border/50"
                            >
                                {key}
                            </button>
                        );
                    })}
                </div>
            </div>
            <TechsaFooter theme="light" className="pb-4" />
        </div>
    );
}

/* ---------- Scan tab ---------- */

// One scan at a time, with a result screen and "Scan next"; full cards ask
// "Mark reward as given" first (Components/CardScanner).
function ScanTab({ api, onScanned, onApiError }) {
    return (
        <div className="flex flex-1 flex-col bg-black">
            <CardScanner
                request={(payload, redeem) => api.post("/api/staff/scan", { payload, ...(redeem && { redeem: true }) })}
                onApiError={onApiError}
                onScanned={onScanned}
                autoFocusInput
            />
        </div>
    );
}

/* ---------- Customers tab ---------- */

function LookupTab({ api, onApiError }) {
    const [query, setQuery] = useState("");
    const [results, setResults] = useState(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const q = query.trim();

        if (q.length < 2) {
            setResults(null);
            return;
        }

        setLoading(true);
        const timeout = setTimeout(() => {
            api.get("/api/staff/customers", { params: { q } })
                .then(({ data }) => {
                    setResults(data.customers);
                    setFailed(false);
                })
                .catch((error) => {
                    if (!onApiError(error)) setFailed(true);
                })
                .finally(() => setLoading(false));
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    return (
        <div className="flex-1 overflow-y-auto px-4 py-5">
            <div className="relative">
                <LuSearch className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-brand-muted" />
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Name or last digits of phone"
                    aria-label="Search customers"
                    className="h-12 w-full rounded-xl border border-brand-border bg-brand-card pl-11 pr-4 text-base text-brand-text outline-none focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/10"
                />
            </div>
            <p className="mt-2 text-xs text-brand-muted">
                For customers without their phone. To add a stamp, scan their
                card.
            </p>

            <div className="mt-4 space-y-2">
                {loading && (
                    <p className="text-sm text-brand-muted">Searching…</p>
                )}
                {!loading && failed && (
                    <p className="text-sm text-red-600">
                        Could not search right now. Check the connection.
                    </p>
                )}
                {!loading && !failed && results?.length === 0 && (
                    <p className="text-sm text-brand-muted">
                        No customers match "{query.trim()}".
                    </p>
                )}

                {!loading &&
                    results?.map((c) => (
                        <div
                            key={c.id}
                            className="rounded-2xl border border-brand-border bg-brand-card p-4 shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="truncate font-medium text-brand-text">
                                        {c.name}
                                    </p>
                                    <p className="text-xs text-brand-muted">
                                        Phone ending {c.phone_ending} ·{" "}
                                        {c.last_visit
                                            ? `last visit ${c.last_visit}`
                                            : "no visits yet"}
                                    </p>
                                </div>
                                {c.reward_ready && (
                                    <span className="shrink-0 rounded-full bg-brand-accent px-2.5 py-1 text-[11px] font-semibold text-brand-accent-text">
                                        Reward ready
                                    </span>
                                )}
                            </div>
                            <div className="mt-3 flex items-center gap-3">
                                <div className="h-2 flex-1 overflow-hidden rounded-full bg-brand-border">
                                    <div
                                        className="h-full rounded-full bg-brand-accent"
                                        style={{
                                            width: `${Math.min(100, (c.stamps / c.max_stamps) * 100)}%`,
                                        }}
                                    />
                                </div>
                                <span className="text-sm font-semibold tabular-nums text-brand-text">
                                    {c.stamps}/{c.max_stamps}
                                </span>
                            </div>
                            <p className="mt-2 text-xs text-brand-muted">
                                {c.rewards_claimed}{" "}
                                {c.rewards_claimed === 1 ? "reward" : "rewards"}{" "}
                                claimed so far
                            </p>
                        </div>
                    ))}
            </div>
        </div>
    );
}

/* ---------- Today tab ---------- */

function Tile({ icon: Icon, label, value, accent }) {
    return (
        <div
            className={`rounded-2xl p-4 shadow-sm ${accent ? "bg-brand-accent text-brand-accent-text" : "border border-brand-border bg-brand-card"}`}
        >
            <Icon className={`h-5 w-5 ${accent ? "" : "text-brand-accent"}`} />
            <p
                className={`mt-3 text-3xl font-bold tabular-nums ${accent ? "" : "text-brand-text"}`}
            >
                {value ?? "–"}
            </p>
            <p
                className={`mt-0.5 text-xs ${accent ? "opacity-85" : "text-brand-muted"}`}
            >
                {label}
            </p>
        </div>
    );
}

function TodayTab({ summary, staffName, onRefresh }) {
    useEffect(() => {
        onRefresh();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div className="flex-1 overflow-y-auto px-4 py-5">
            <h2 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">
                You today, {staffName}
            </h2>
            <div className="mt-3 grid grid-cols-2 gap-3">
                <Tile
                    icon={LuStamp}
                    label="Stamps you gave"
                    value={summary?.my_stamps_today}
                    accent
                />
                <Tile
                    icon={LuGift}
                    label="Rewards you redeemed"
                    value={summary?.my_redeemed_today}
                    accent
                />
            </div>

            <h2 className="mt-6 text-xs font-semibold uppercase tracking-wide text-brand-muted">
                Whole shop today
            </h2>
            <div className="mt-3 grid grid-cols-2 gap-3">
                <Tile
                    icon={LuStamp}
                    label="Stamps"
                    value={summary?.stamps_today}
                />
                <Tile
                    icon={LuGift}
                    label="Rewards redeemed"
                    value={summary?.redeemed_today}
                />
            </div>
        </div>
    );
}

/* ---------- Page ---------- */

const TABS = [
    { id: "scan", label: "Scan", icon: LuScanLine },
    { id: "lookup", label: "Customers", icon: LuUsers },
    { id: "today", label: "Today", icon: LuChartColumn },
];

export default function Dashboard() {
    const [token] = useState(() => {
        try {
            return window.localStorage.getItem(STAFF_TOKEN_KEY);
        } catch {
            return null;
        }
    });

    const [authState, setAuthState] = useState("loading"); // loading | ready | unauthenticated | error
    const [retryKey, setRetryKey] = useState(0);
    const [me, setMe] = useState(null);
    const [tab, setTab] = useState("scan");
    const [summary, setSummary] = useState(null);

    // One axios instance carrying the device's bearer token.
    const [api] = useState(() =>
        axios.create({
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        }),
    );

    useEffect(() => {
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/sw.js").catch(() => {});
        }
    }, []);

    /**
     * Shared handling for the two "you've lost access" answers. Returns true
     * when it dealt with the error. Only a 401 forgets the device token - a
     * signed-out staff member (403) just goes back to the PIN screen.
     */
    function handleApiError(error) {
        const status = error.response?.status;

        if (status === 401) {
            window.localStorage.removeItem(STAFF_TOKEN_KEY);
            setAuthState("unauthenticated");
            return true;
        }

        if (
            status === 403 &&
            error.response.data?.code === "staff_signed_out"
        ) {
            setMe((m) => (m ? { ...m, staff: null } : m));
            return true;
        }

        return false;
    }

    useEffect(() => {
        if (!token) {
            setAuthState("unauthenticated");
            return;
        }

        api.get("/api/staff/me")
            .then(({ data }) => {
                setMe(data);
                setAuthState("ready");
            })
            .catch((error) => {
                // Being offline or a server hiccup must not wipe the token -
                // that would force the owner to re-approve the device.
                if (!handleApiError(error)) setAuthState("error");
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [token, retryKey]);

    function refreshSummary() {
        api.get("/api/staff/summary")
            .then(({ data }) => setSummary(data))
            .catch(handleApiError);
    }

    useEffect(() => {
        if (me?.staff) {
            setTab("scan");
            refreshSummary();
        } else {
            setSummary(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [me?.staff?.id]);

    function signOut() {
        api.post("/api/staff/sign-out").catch(() => {});
        // Re-read the staff list too, in case the owner changed it.
        setMe((m) => ({ ...m, staff: null }));
        setRetryKey((n) => n + 1);
    }

    const headTags = (
        <Head title="Staff">
            <link rel="manifest" href="/manifest.webmanifest" />
            <meta name="theme-color" content="#0f2a46" />
            <meta name="apple-mobile-web-app-capable" content="yes" />
            <meta
                name="apple-mobile-web-app-status-bar-style"
                content="black-translucent"
            />
            <meta name="apple-mobile-web-app-title" content="Staff" />
            <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        </Head>
    );

    if (authState === "unauthenticated") {
        return (
            <>
                {headTags}
                <CenteredMessage
                    icon={LuStore}
                    title="This device is not set up"
                >
                    <p className="mt-2 text-sm text-brand-muted">
                        Ask the shop owner to add this device in Dashboard →
                        Staff and scan the QR code it shows.
                    </p>
                </CenteredMessage>
            </>
        );
    }

    if (authState === "error") {
        return (
            <>
                {headTags}
                <OfflineBanner />
                <CenteredMessage title="Can't reach the server">
                    <p className="mt-2 text-sm text-brand-muted">
                        Check the connection, then try again.
                    </p>
                    <button
                        onClick={() => {
                            setAuthState("loading");
                            setRetryKey((n) => n + 1);
                        }}
                        className="mt-4 rounded-xl bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text"
                    >
                        Try again
                    </button>
                </CenteredMessage>
            </>
        );
    }

    if (authState === "loading" || !me) {
        return (
            <>
                {headTags}
                <div className="flex min-h-dvh items-center justify-center bg-brand-bg">
                    <span
                        className="h-8 w-8 animate-spin rounded-full border-2 border-brand-border border-t-brand-accent"
                        aria-label="Loading"
                    />
                </div>
            </>
        );
    }

    if (!me.staff) {
        return (
            <>
                {headTags}
                <OfflineBanner />
                <PinSignIn
                    shopName={me.shop_name}
                    members={me.staff_members}
                    api={api}
                    onSignedIn={(staff) => setMe((m) => ({ ...m, staff }))}
                />
            </>
        );
    }

    return (
        <>
            {headTags}
            <OfflineBanner />

            <div className="flex h-dvh flex-col bg-brand-bg">
                <header className="flex items-center justify-between gap-3 border-b border-brand-border bg-brand-card px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-accent/10 font-semibold text-brand-accent">
                            {me.staff.name.charAt(0).toUpperCase()}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-brand-text">
                                {me.staff.name}
                            </p>
                            <p className="truncate text-xs text-brand-muted">
                                {me.shop_name} · {me.device_name}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                        {summary && (
                            <div className="text-right">
                                <p className="text-lg font-bold leading-none tabular-nums text-brand-text">
                                    {summary.my_stamps_today}
                                </p>
                                <p className="text-[11px] text-brand-muted">
                                    your stamps
                                </p>
                            </div>
                        )}
                        <button
                            type="button"
                            onClick={signOut}
                            className="flex items-center gap-1.5 rounded-xl border border-brand-border px-3 py-2 text-xs font-medium text-brand-text active:bg-brand-bg"
                        >
                            <LuLogOut className="h-4 w-4" /> Switch
                        </button>
                    </div>
                </header>

                <main className="flex min-h-0 flex-1 flex-col">
                    {tab === "scan" && (
                        <ScanTab
                            api={api}
                            onScanned={refreshSummary}
                            onApiError={handleApiError}
                        />
                    )}
                    {tab === "lookup" && (
                        <LookupTab api={api} onApiError={handleApiError} />
                    )}
                    {tab === "today" && (
                        <TodayTab
                            summary={summary}
                            staffName={me.staff.name}
                            onRefresh={refreshSummary}
                        />
                    )}
                </main>

                <nav className="grid grid-cols-3 border-t border-brand-border bg-brand-card pb-[env(safe-area-inset-bottom)]">
                    {TABS.map(({ id, label, icon: Icon }) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => setTab(id)}
                            aria-current={tab === id ? "page" : undefined}
                            className={`flex flex-col items-center gap-1 py-3 text-xs font-medium ${tab === id ? "text-brand-accent" : "text-brand-muted"}`}
                        >
                            <Icon className="h-5 w-5" />
                            {label}
                        </button>
                    ))}
                </nav>
            </div>
        </>
    );
}
