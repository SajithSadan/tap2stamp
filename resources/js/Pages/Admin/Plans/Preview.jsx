import { Link, router } from "@inertiajs/react";
import { LuArrowLeft, LuEye } from "react-icons/lu";
import CountrySelect from "@/Components/CountrySelect";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { inputClass, secondaryButton } from "@/Components/Dashboard/Ui";
import PlanCards from "@/Components/PlanCards";

/**
 * Admin → Landing page → Owner preview: the plans exactly as an owner in `country` sees
 * them on their Plan page - only the offered plans, priced in that
 * country's currency (no price there = "Ask us for the price").
 */
export default function Preview({ plans, countries, country, currency }) {
    const pick = (code) => router.get("/admin/plans/preview", { country: code }, { preserveScroll: true, preserveState: true, replace: true });
    const missing = plans.filter((p) => !p.price).length;

    return (
        <AdminLayout
            title="Pricing preview"
            description="What owners see on their Plan page, by country."
            actions={
                <Link href="/admin/landing-page" className={secondaryButton}>
                    <LuArrowLeft className="h-4 w-4" /> Back to landing & plans
                </Link>
            }
        >
            <div className="mb-5 flex flex-wrap items-end gap-x-4 gap-y-2">
                <div className="w-72 max-w-full">
                    <label htmlFor="preview-country" className="mb-1.5 block text-sm font-medium text-brand-text">
                        Owner's country
                    </label>
                    <CountrySelect id="preview-country" value={country} onChange={pick} countries={countries} className={inputClass} />
                </div>
                <p className="pb-2.5 text-sm text-brand-muted">
                    {currency ? `Prices in ${currency}` : "No currency for this country"}
                    {missing > 0 && ` · ${missing} ${missing === 1 ? "plan has" : "plans have"} no price here`}
                </p>
            </div>

            {plans.length === 0 ? (
                <p className="flex items-center gap-2 text-sm text-brand-muted">
                    <LuEye className="h-4 w-4" /> No plans are offered to owners.
                </p>
            ) : (
                <PlanCards plans={plans} />
            )}
        </AdminLayout>
    );
}
