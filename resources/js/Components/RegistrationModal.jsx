import { motion } from 'framer-motion';
import { useState } from 'react';
import { ChevronDownIcon } from '@/Components/Icons';

function toDigitsOnly(value) {
    return value.replace(/\D/g, '');
}

/** Strips a leading 0 (people often type it out of habit despite the +44 prefix) and caps at 10 digits. */
function normaliseDigits(raw) {
    let digits = toDigitsOnly(raw);
    if (digits.startsWith('0')) digits = digits.slice(1);

    return digits.slice(0, 10);
}

export default function RegistrationModal({ shopName, rewardTitle, submitting, errors, onSubmit }) {
    const [name, setName] = useState('');
    const [phoneDigits, setPhoneDigits] = useState('');
    const [localErrors, setLocalErrors] = useState({});
    const [shake, setShake] = useState(0);

    function validate() {
        const next = {};

        if (name.trim() === '') {
            next.name = 'Name is required.';
        }

        if (!/^7\d{9}$/.test(phoneDigits)) {
            next.phone = 'Enter a 10-digit UK mobile number starting with 7.';
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

        onSubmit(name.trim(), `+44${phoneDigits}`);
    }

    const nameError = localErrors.name ?? errors.name?.[0];
    const phoneError = localErrors.phone ?? errors.phone?.[0];

    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-20 flex items-end justify-center bg-black/50 p-4 sm:items-center"
        >
            <motion.div
                initial={{ opacity: 0, y: 40, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1, x: 0 }}
                exit={{ opacity: 0, y: 20, scale: 0.98 }}
                transition={{ type: 'spring', stiffness: 300, damping: 28 }}
                className="w-full max-w-sm rounded-brand border border-brand-border bg-brand-card p-6 shadow-xl"
            >
                <motion.div
                    key={shake}
                    animate={shake > 0 ? { x: [0, -8, 8, -5, 5, 0] } : {}}
                    transition={{ duration: 0.4 }}
                >
                    <h1 className="font-heading text-lg font-semibold text-brand-text">{shopName}</h1>
                    <p className="mt-1 text-sm text-brand-muted">{rewardTitle}</p>
                    <p className="mt-3 text-sm text-brand-muted">
                        First visit? Enter your name and mobile number to start your card.
                    </p>

                    <form onSubmit={handleSubmit} noValidate className="mt-4 space-y-3">
                        <div>
                            <label className="block text-xs font-medium text-brand-muted">Name</label>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                className="mt-1 w-full rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none transition-colors focus:border-brand-accent"
                                autoComplete="name"
                            />
                            {nameError && <p className="mt-1 text-xs text-red-600">{nameError}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-brand-muted">Mobile number</label>
                            <div className="mt-1 flex overflow-hidden rounded border border-brand-border focus-within:border-brand-accent">
                                <div className="relative flex items-center border-r border-brand-border bg-brand-bg">
                                    {/* UK-only for now (see CLAUDE.md) — a real <select> so the
                                        country code reads as an interactive field, not static text. */}
                                    <select
                                        value="GB"
                                        onChange={() => {}}
                                        aria-label="Country code"
                                        className="appearance-none bg-transparent py-2 pl-3 pr-7 text-sm text-brand-muted outline-none"
                                    >
                                        <option value="GB">🇬🇧 +44</option>
                                    </select>
                                    <ChevronDownIcon
                                        className="pointer-events-none absolute right-2 h-3.5 w-3.5 text-brand-muted"
                                        aria-hidden="true"
                                    />
                                </div>
                                <input
                                    type="tel"
                                    inputMode="numeric"
                                    value={phoneDigits}
                                    onChange={(e) => setPhoneDigits(normaliseDigits(e.target.value))}
                                    placeholder="7911 123456"
                                    className="min-w-0 flex-1 px-3 py-2 text-sm text-brand-text outline-none"
                                    autoComplete="tel-national"
                                />
                            </div>
                            {phoneError && <p className="mt-1 text-xs text-red-600">{phoneError}</p>}
                        </div>

                        <motion.button
                            whileTap={{ scale: 0.97 }}
                            type="submit"
                            disabled={submitting}
                            className="w-full rounded-brand bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
                        >
                            {submitting ? (
                                <span className="flex items-center justify-center gap-2">
                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-brand-accent-text/40 border-t-brand-accent-text" />
                                    Starting your card…
                                </span>
                            ) : (
                                'Start my card'
                            )}
                        </motion.button>
                    </form>
                </motion.div>
            </motion.div>
        </motion.div>
    );
}
