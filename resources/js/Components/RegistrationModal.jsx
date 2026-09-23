import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { GiCoffeeBeans } from 'react-icons/gi';
import { LuArrowRight, LuCheck, LuChevronDown, LuCreditCard, LuStore, LuUser } from 'react-icons/lu';
import TechsaFooter from '@/Components/TechsaFooter';

// Inline SVG flags: Windows doesn't render flag emoji (it shows "GB" letters instead).
function UkFlag(props) {
    return (
        <svg viewBox="0 0 60 30" {...props}>
            <clipPath id="uk-flag-t">
                <path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z" />
            </clipPath>
            <path d="M0,0 v30 h60 v-30 z" fill="#012169" />
            <path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" strokeWidth="6" />
            <path d="M0,0 L60,30 M60,0 L0,30" clipPath="url(#uk-flag-t)" stroke="#C8102E" strokeWidth="4" />
            <path d="M30,0 v30 M0,15 h60" stroke="#fff" strokeWidth="10" />
            <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" strokeWidth="6" />
        </svg>
    );
}

function IndiaFlag(props) {
    return (
        <svg viewBox="0 0 30 20" {...props}>
            <rect width="30" height="20" fill="#fff" />
            <rect width="30" height="6.67" fill="#FF9933" />
            <rect y="13.33" width="30" height="6.67" fill="#138808" />
            <circle cx="15" cy="10" r="2.6" fill="none" stroke="#000080" strokeWidth="0.6" />
            <circle cx="15" cy="10" r="0.6" fill="#000080" />
        </svg>
    );
}

// Each country's local number: 10 digits after the dial code, leading 0 stripped.
const COUNTRIES = [
    {
        code: 'GB',
        name: 'United Kingdom',
        dial: '+44',
        Flag: UkFlag,
        pattern: /^7\d{9}$/,
        placeholder: '7911 123456',
        hint: 'e.g. 7911 123456',
    },
    {
        code: 'IN',
        name: 'India',
        dial: '+91',
        Flag: IndiaFlag,
        pattern: /^[6-9]\d{9}$/,
        placeholder: '98765 43210',
        hint: 'e.g. 98765 43210',
    },
];

/** Strips a leading 0 (people often type it out of habit despite the dial code) and caps at 10 digits. */
function normaliseDigits(raw) {
    let digits = raw.replace(/\D/g, '');
    if (digits.startsWith('0')) digits = digits.slice(1);

    return digits.slice(0, 10);
}

function FlagBadge({ Flag }) {
    return <Flag className="h-4 w-6 shrink-0 overflow-hidden rounded-[3px] shadow-sm ring-1 ring-black/10" aria-hidden="true" />;
}

function CountryPicker({ country, onChange }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    useEffect(() => {
        if (!open) return;

        const onPointerDown = (e) => {
            if (!rootRef.current?.contains(e.target)) setOpen(false);
        };
        const onKeyDown = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    return (
        // `static` so the dropdown positions against the whole phone field
        // (its nearest relative parent), not just this button.
        <div ref={rootRef} className="static flex">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-label={`Country code: ${country.name} ${country.dial}`}
                className="flex items-center gap-2 rounded-l-xl border-r border-brand-border pl-3 pr-2 outline-none transition-colors hover:bg-brand-border/40 focus-visible:bg-brand-border/40"
            >
                <FlagBadge Flag={country.Flag} />
                <span className="text-base font-semibold text-brand-text">{country.dial}</span>
                <motion.span animate={{ rotate: open ? 180 : 0 }} className="text-brand-muted">
                    <LuChevronDown className="h-4 w-4" aria-hidden="true" />
                </motion.span>
            </button>

            <AnimatePresence>
                {open && (
                    <motion.ul
                        role="listbox"
                        aria-label="Choose country"
                        initial={{ opacity: 0, y: -6, scale: 0.98 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: -6, scale: 0.98 }}
                        transition={{ duration: 0.16 }}
                        className="absolute inset-x-0 top-full z-10 mt-2 origin-top overflow-hidden rounded-xl border border-brand-border bg-brand-card p-1 shadow-xl"
                    >
                        {COUNTRIES.map((c) => {
                            const selected = c.code === country.code;

                            return (
                                <li key={c.code} role="option" aria-selected={selected}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onChange(c);
                                            setOpen(false);
                                        }}
                                        className={`flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left transition-colors ${
                                            selected ? 'bg-brand-accent/10' : 'hover:bg-brand-bg'
                                        }`}
                                    >
                                        <FlagBadge Flag={c.Flag} />
                                        <span className="flex-1 text-sm font-medium text-brand-text">{c.name}</span>
                                        <span className="text-sm tabular-nums text-brand-muted">{c.dial}</span>
                                        <span className={`text-sm text-brand-accent ${selected ? 'opacity-100' : 'opacity-0'}`} aria-hidden="true">
                                            ✓
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </motion.ul>
                )}
            </AnimatePresence>
        </div>
    );
}

const fieldShell =
    'rounded-xl border bg-brand-bg/70 transition-all focus-within:border-brand-accent/60 focus-within:bg-brand-card focus-within:ring-4 focus-within:ring-brand-accent/10';

// text-base (16px): iOS Safari zooms the whole page into anything smaller on focus.
const inputText = 'text-base font-medium text-brand-text placeholder:font-normal placeholder:text-brand-muted/60 outline-none';

// Content settles in just after the sheet lands, for a softer entrance.
function Reveal({ index, children, className = '' }) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.35 + index * 0.06, duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className={className}
        >
            {children}
        </motion.div>
    );
}

// Dark backdrop - the shop's banner photo blurred (like a café seen through glass), or soft
// accent glows without one. Hides the card page behind instead of blurring it through.
function Backdrop({ bannerUrl }) {
    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.4 }}
            // Always dark (not bg-brand-text): on a dark theme the text colour is light.
            className="fixed inset-0 overflow-hidden bg-neutral-950"
            aria-hidden="true"
        >
            {bannerUrl && (
                <>
                    {/* scale-110 hides the blur's soft transparent edges. */}
                    <img src={bannerUrl} alt="" className="absolute inset-0 h-full w-full scale-110 object-cover blur-md" />
                    <div className="absolute inset-0 bg-black/55" />
                </>
            )}
            <div className="absolute -left-24 -top-20 h-80 w-80 rounded-full bg-brand-accent/35 blur-3xl" />
            <div className="absolute -right-24 bottom-10 h-80 w-72 rounded-full bg-brand-accent/20 blur-3xl" />
            <div className="absolute left-1/2 top-1/2 hidden h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-accent/15 blur-3xl sm:block" />
            <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(0,0,0,0.5)_100%)]" />
        </motion.div>
    );
}

// Placeholder until shops get their own slogan field - pass `slogan` to override.
const DEFAULT_SLOGAN = ['Great coffee', 'Good vibes', 'Together'];

export default function RegistrationModal({ shopName, bannerUrl, slogan = DEFAULT_SLOGAN, submitting, errors, onSubmit }) {
    const [name, setName] = useState('');
    const [country, setCountry] = useState(COUNTRIES[0]);
    const [phoneDigits, setPhoneDigits] = useState('');
    // Unticked by default - UK marketing consent must be an active opt-in.
    const [marketingConsent, setMarketingConsent] = useState(false);
    const [localErrors, setLocalErrors] = useState({});
    const [shake, setShake] = useState(0);

    function validate() {
        const next = {};

        if (name.trim() === '') {
            next.name = 'Please enter your name.';
        }

        if (!country.pattern.test(phoneDigits)) {
            next.phone = `Enter a valid ${country.name} mobile number, ${country.hint}.`;
        }

        setLocalErrors(next);

        return Object.keys(next).length === 0;
    }

    function handleSubmit(event) {
        event.preventDefault();

        if (!validate()) {
            setShake((n) => n + 1);
            return;
        }

        onSubmit(name.trim(), `${country.dial}${phoneDigits}`, marketingConsent);
    }

    const nameError = localErrors.name ?? errors.name?.[0];
    const phoneError = localErrors.phone ?? errors.phone?.[0];

    return (
        <div className="fixed inset-0 z-20 overflow-y-auto">
            <Backdrop bannerUrl={bannerUrl} />

            <div className="relative mx-auto flex min-h-full max-w-sm flex-col items-center px-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:justify-center sm:py-10">
                <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0 }}
                    transition={{ delay: 0.1, duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                    className="flex w-full flex-1 flex-col items-center justify-center pb-5 pt-[max(1.5rem,env(safe-area-inset-top))] text-center sm:flex-none sm:pb-6 sm:pt-0"
                >
                    <span className="flex h-20 w-20 items-center justify-center rounded-full bg-brand-card text-brand-accent shadow-[0_10px_30px_rgba(0,0,0,0.45)] ring-4 ring-white/10">
                        <LuStore className="h-9 w-9" />
                    </span>
                    <h1 id="register-title" className="mt-4 font-heading text-4xl font-semibold leading-tight text-white">
                        {shopName}
                    </h1>
                    {slogan.length > 0 && (
                        <p className="mt-1.5 flex w-full flex-wrap items-center justify-center gap-x-2 px-7 text-xs tracking-[0.12em] text-white/70">
                            {slogan.map((part, i) => (
                                <span key={part} className="flex items-center gap-x-2 whitespace-nowrap">
                                    {i > 0 && <span aria-hidden="true">•</span>}
                                    {part}
                                </span>
                            ))}
                        </p>
                    )}
                </motion.div>

                <motion.div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="register-title"
                    initial={{ y: 80, opacity: 0 }}
                    animate={{ y: 0, opacity: 1 }}
                    exit={{ y: 80, opacity: 0 }}
                    transition={{ type: 'spring', stiffness: 220, damping: 28, delay: 0.15 }}
                    className="relative w-full rounded-3xl bg-brand-card px-7 pb-2 pt-2.5 sm:pt-7 shadow-[0_30px_60px_-15px_rgba(0,0,0,0.6)]"
                >
                    <div className="mx-auto mb-4 h-1 w-10 rounded-full bg-brand-accent/30 sm:hidden" aria-hidden="true" />

                    <motion.div key={shake} animate={shake > 0 ? { x: [0, -8, 8, -5, 5, 0] } : {}} transition={{ duration: 0.4 }}>
                        <form onSubmit={handleSubmit} noValidate className="space-y-5">
                            <Reveal index={0}>
                                <label htmlFor="register-name" className="mb-1.5 block text-sm font-medium text-brand-text">
                                    Your name
                                </label>
                                <div className={`flex h-12 items-center ${fieldShell} ${nameError ? 'border-red-500' : 'border-brand-border'}`}>
                                    <LuUser className="ml-3 h-[18px] w-[18px] shrink-0 text-brand-muted" aria-hidden="true" />
                                    <input
                                        id="register-name"
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder="e.g. Jamie"
                                        autoComplete="given-name"
                                        enterKeyHint="next"
                                        aria-invalid={Boolean(nameError)}
                                        className={`h-full min-w-0 flex-1 rounded-r-xl bg-transparent px-2.5 ${inputText}`}
                                    />
                                </div>
                                {nameError && <p className="mt-1 text-xs text-red-600">{nameError}</p>}
                            </Reveal>

                            <Reveal index={1}>
                                <label htmlFor="register-phone" className="mb-1.5 block text-sm font-medium text-brand-text">
                                    Mobile number
                                </label>
                                <div className={`relative flex h-12 ${fieldShell} ${phoneError ? 'border-red-500' : 'border-brand-border'}`}>
                                    <CountryPicker country={country} onChange={setCountry} />
                                    <input
                                        id="register-phone"
                                        type="tel"
                                        inputMode="numeric"
                                        value={phoneDigits}
                                        onChange={(e) => setPhoneDigits(normaliseDigits(e.target.value))}
                                        placeholder={country.placeholder}
                                        autoComplete="tel-national"
                                        enterKeyHint="done"
                                        aria-invalid={Boolean(phoneError)}
                                        className={`min-w-0 flex-1 rounded-r-xl bg-transparent px-3 tracking-wide ${inputText}`}
                                    />
                                </div>
                                {phoneError ? (
                                    <p className="mt-1 text-xs text-red-600">{phoneError}</p>
                                ) : (
                                    <p className="mt-1 text-xs text-brand-muted">We use this to find your card if you switch phones.</p>
                                )}
                            </Reveal>

                            <Reveal index={2}>
                                <label
                                    htmlFor="register-consent"
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-transparent bg-brand-bg/80 px-3.5 py-3 transition-colors has-[:checked]:border-brand-accent/30 has-[:checked]:bg-brand-accent/5"
                                >
                                    <span className="relative flex h-5 w-5 shrink-0">
                                        <input
                                            id="register-consent"
                                            type="checkbox"
                                            checked={marketingConsent}
                                            onChange={(e) => setMarketingConsent(e.target.checked)}
                                            className="peer h-5 w-5 cursor-pointer appearance-none rounded-md border-[1.5px] border-brand-muted/60 bg-brand-card transition-colors checked:border-brand-accent checked:bg-brand-accent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent/20"
                                        />
                                        <LuCheck
                                            strokeWidth={3.5}
                                            className="pointer-events-none absolute inset-0 m-auto h-3.5 w-3.5 text-brand-accent-text opacity-0 transition-opacity peer-checked:opacity-100"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="text-[13px] leading-snug text-brand-text">
                                        Yes, text me offers and news from {shopName} <span className="text-brand-muted">(optional)</span>
                                    </span>
                                </label>
                            </Reveal>

                            {errors.general && (
                                <p role="alert" className="text-center text-xs text-red-600">
                                    {errors.general}
                                </p>
                            )}

                            <Reveal index={3}>
                                <motion.button
                                    whileTap={{ scale: 0.98 }}
                                    type="submit"
                                    disabled={submitting}
                                    className="relative flex h-12 w-full items-center justify-center rounded-full bg-gradient-to-b from-brand-accent to-brand-accent/85 px-5 text-[15px] font-semibold text-brand-accent-text shadow-[0_4px_10px_-4px_rgba(0,0,0,0.25),inset_0_1px_0_rgba(255,255,255,0.15)] transition-opacity disabled:opacity-60"
                                >
                                    {submitting ? (
                                        <span className="flex items-center gap-2">
                                            <span className="h-4 w-4 animate-spin rounded-full border-2 border-brand-accent-text/40 border-t-brand-accent-text" />
                                            Starting your card…
                                        </span>
                                    ) : (
                                        <>
                                            <span className="flex items-center gap-3">
                                                <LuCreditCard className="h-[18px] w-[18px]" aria-hidden="true" />
                                                <span className="h-4 w-px bg-brand-accent-text/35" aria-hidden="true" />
                                                Start my card
                                            </span>
                                            <LuArrowRight className="absolute right-5 h-[18px] w-[18px]" aria-hidden="true" />
                                        </>
                                    )}
                                </motion.button>
                            </Reveal>
                        </form>

                        <Reveal index={4} className="mt-5">
                            <div className="flex items-center justify-center gap-3" aria-hidden="true">
                                <span className="h-px w-20 bg-gradient-to-r from-transparent to-brand-accent/40" />
                                <GiCoffeeBeans className="h-5 w-5 text-brand-accent" />
                                <span className="h-px w-20 bg-gradient-to-l from-transparent to-brand-accent/40" />
                            </div>
                        </Reveal>

                        <Reveal index={5} className="mt-1">
                            <TechsaFooter theme="brand" size="sm" />
                        </Reveal>
                    </motion.div>
                </motion.div>
            </div>
        </div>
    );
}
