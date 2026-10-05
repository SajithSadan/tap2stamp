import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { LuBellOff, LuCircleCheck } from "react-icons/lu";
import { useDocumentTheme } from "@/lib/theme";

/** /u/{token}: the Unsubscribe button on a shop's WhatsApp offers. */
export default function Unsubscribe({ shopName, subscribed, token, theme }) {
    useDocumentTheme(theme);
    const [processing, setProcessing] = useState(false);

    function unsubscribe() {
        setProcessing(true);
        router.post(`/u/${token}`, {}, { onFinish: () => setProcessing(false) });
    }

    return (
        <>
            <Head title={`Offers from ${shopName}`} />
            <main className="flex min-h-screen items-center justify-center bg-brand-bg px-4 py-10">
                <div className="w-full max-w-sm rounded-brand border border-brand-border bg-brand-card p-6 text-center">
                    {subscribed ? (
                        <>
                            <LuBellOff className="mx-auto h-9 w-9 text-brand-muted" />
                            <h1 className="mt-4 font-heading text-xl font-semibold text-brand-text">Stop offers from {shopName}?</h1>
                            <p className="mt-2 text-sm text-brand-muted">
                                You won't get their WhatsApp offers any more. Your loyalty card and stamps stay as they are.
                            </p>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={unsubscribe}
                                className="mt-6 w-full rounded-full bg-brand-accent px-5 py-3 text-sm font-semibold text-brand-accent-text disabled:opacity-60"
                            >
                                {processing ? "Unsubscribing…" : "Unsubscribe"}
                            </button>
                        </>
                    ) : (
                        <>
                            <LuCircleCheck className="mx-auto h-9 w-9 text-brand-accent" />
                            <h1 className="mt-4 font-heading text-xl font-semibold text-brand-text">You're unsubscribed</h1>
                            <p className="mt-2 text-sm text-brand-muted">
                                {shopName} won't send you WhatsApp offers any more. Your loyalty card and stamps are unaffected.
                            </p>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
