import { useForm } from "@inertiajs/react";
import { LuStore } from "react-icons/lu";
import IndustryPicker from "@/Components/IndustryPicker";
import { inputClass, primaryButton } from "@/Components/Dashboard/Ui";

/**
 * Owners of shops set up before sign-up asked: "What kind of business is
 * it?", once, on whichever dashboard page they open (shared `industryPrompt`
 * prop). One tap + Save; gone for good once answered.
 */
export default function IndustryPrompt({ industries }) {
    const form = useForm({ industry: "", industry_other: "" });

    function submit(e) {
        e.preventDefault();
        form.put("/dashboard/industry", { preserveScroll: true, preserveState: true });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4">
            <form
                onSubmit={submit}
                noValidate
                role="dialog"
                aria-modal="true"
                aria-labelledby="industry-prompt-title"
                className="flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-brand-card shadow-xl sm:max-w-2xl sm:rounded-2xl"
            >
                <div className="flex items-start gap-3 border-b border-brand-border p-5">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                        <LuStore className="h-5 w-5" />
                    </span>
                    <div>
                        <h2 id="industry-prompt-title" className="font-heading text-lg font-semibold text-brand-text">
                            What kind of business is it?
                        </h2>
                        <p className="mt-0.5 text-sm text-brand-muted">
                            One quick question, so we can share tips and offers that suit your business.
                        </p>
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto p-5">
                    <IndustryPicker
                        id="prompt_industry"
                        industries={industries}
                        value={form.data.industry}
                        other={form.data.industry_other}
                        onChange={(key) => form.setData("industry", key)}
                        onOtherChange={(v) => form.setData("industry_other", v)}
                        inputClassName={inputClass}
                        otherError={form.errors.industry_other}
                    />
                    {form.errors.industry && (
                        <p role="alert" className="mt-3 text-sm text-red-600">
                            {form.errors.industry}
                        </p>
                    )}
                </div>

                <div className="border-t border-brand-border p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pb-4">
                    <button type="submit" disabled={!form.data.industry || form.processing} className={`${primaryButton} w-full`}>
                        {form.processing ? "Saving…" : "Save"}
                    </button>
                </div>
            </form>
        </div>
    );
}
