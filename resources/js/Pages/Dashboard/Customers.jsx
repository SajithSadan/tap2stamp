import { router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { LuDownload, LuSearch, LuUsers, LuX } from "react-icons/lu";
import CustomerWhatsApp from "@/Components/Dashboard/CustomerWhatsApp";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import {
    Avatar,
    EmptyState,
    inputClass,
    Pagination,
    Panel,
    secondaryButton,
} from "@/Components/Dashboard/Ui";

function StampProgress({ stamps, max }) {
    const full = stamps >= max;

    return (
        <div className="flex items-center gap-2">
            <div className="h-1.5 w-20 overflow-hidden rounded-full bg-brand-border">
                <div
                    className="h-full rounded-full bg-brand-accent"
                    style={{ width: `${Math.min(100, (stamps / max) * 100)}%` }}
                />
            </div>
            <span
                className={`text-xs tabular-nums ${full ? "font-semibold text-brand-accent" : "text-brand-muted"}`}
            >
                {stamps}/{max}
            </span>
        </div>
    );
}

function ConsentBadge({ on }) {
    return on ? (
        <span className="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-800">
            Opted in
        </span>
    ) : (
        <span className="text-xs text-brand-muted">–</span>
    );
}

/** The URL params for a search within the current group (?group=drifting from Insights). */
const params = (q, group) => ({
    ...(q ? { q } : {}),
    ...(group ? { group: group.key } : {}),
});

export default function Customers({
    shop,
    search,
    group,
    customers,
    maxStamps,
}) {
    const [query, setQuery] = useState(search);
    const firstRun = useRef(true);
    const exportQuery = new URLSearchParams(params(query, group)).toString();

    // Debounced live search - keeps the URL (?q=) in sync so it survives a refresh.
    useEffect(() => {
        if (firstRun.current) {
            firstRun.current = false;
            return;
        }

        const timeout = setTimeout(() => {
            router.get("/dashboard/customers", params(query, group), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timeout);
    }, [query]);

    function clearGroup() {
        router.get("/dashboard/customers", params(query, null), {
            preserveScroll: true,
        });
    }

    return (
        <OwnerLayout
            shop={shop}
            title="Customers"
            description="Everyone who has a card at your shop."
        >
            <Panel
                bodyClassName=""
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {`${customers.total} ${customers.total === 1 ? "customer" : "customers"}`}
                        {group && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-brand-accent/10 py-0.5 pl-2.5 pr-1 text-xs font-medium text-brand-text">
                                {group.label}
                                <button
                                    type="button"
                                    onClick={clearGroup}
                                    aria-label={`Show all customers, not just ${group.label.toLowerCase()}`}
                                    className="rounded-full p-0.5 text-brand-muted hover:bg-brand-accent/20 hover:text-brand-text"
                                >
                                    <LuX className="h-3.5 w-3.5" />
                                </button>
                            </span>
                        )}
                    </span>
                }
                action={
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <div className="relative w-full sm:w-64">
                            <LuSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-muted" />
                            <input
                                type="search"
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search name or phone"
                                aria-label="Search customers by name or phone"
                                className={`${inputClass} pl-9`}
                            />
                        </div>
                        <a
                            href={`/dashboard/customers/export${exportQuery ? `?${exportQuery}` : ""}`}
                            className={`${secondaryButton} shrink-0`}
                        >
                            <LuDownload className="h-4 w-4" /> Download CSV
                        </a>
                    </div>
                }
            >
                {customers.data.length === 0 ? (
                    <EmptyState
                        icon={LuUsers}
                        title={
                            search
                                ? "No customers match that search"
                                : group
                                  ? `No customers ${group.label.toLowerCase()}`
                                  : "No customers yet"
                        }
                    >
                        {search
                            ? "Try a different name."
                            : group
                              ? "Good news - nobody's in this list right now."
                              : "Customers appear here after they scan your counter QR code and sign up."}
                    </EmptyState>
                ) : (
                    <>
                        {/* Table on wider screens */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                        <th className="px-5 py-3 font-medium">
                                            Name
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Phone
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Stamps
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Rewards
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Last visit
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Joined
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Texts
                                        </th>
                                        <th className="px-5 py-3">
                                            <span className="sr-only">
                                                WhatsApp
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-brand-border">
                                    {customers.data.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="hover:bg-brand-bg/60"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    <Avatar name={c.name} />
                                                    <span className="font-medium text-brand-text">
                                                        {c.name}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 tabular-nums text-brand-text">
                                                {c.phone}
                                            </td>
                                            <td className="px-5 py-3">
                                                <StampProgress
                                                    stamps={c.stamps}
                                                    max={maxStamps}
                                                />
                                            </td>
                                            <td className="px-5 py-3 tabular-nums text-brand-text">
                                                {c.rewards_claimed}
                                            </td>
                                            <td className="px-5 py-3 text-brand-muted">
                                                {c.last_visit ?? "Not yet"}
                                            </td>
                                            <td className="px-5 py-3 text-brand-muted">
                                                {c.joined}
                                            </td>
                                            <td className="px-5 py-3">
                                                <ConsentBadge
                                                    on={c.marketing_consent}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <CustomerWhatsApp customer={c} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Cards on phones */}
                        <ul className="divide-y divide-brand-border md:hidden">
                            {customers.data.map((c) => (
                                <li
                                    key={c.id}
                                    className="flex items-center gap-3 px-5 py-3"
                                >
                                    <Avatar name={c.name} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-brand-text">
                                            {c.name}
                                        </p>
                                        <p className="text-xs tabular-nums text-brand-muted">
                                            {c.phone}
                                        </p>
                                        <p className="text-xs text-brand-muted">
                                            {c.rewards_claimed}{" "}
                                            {c.rewards_claimed === 1
                                                ? "reward"
                                                : "rewards"}{" "}
                                            · last visit{" "}
                                            {c.last_visit ?? "not yet"}
                                        </p>
                                    </div>
                                    <StampProgress
                                        stamps={c.stamps}
                                        max={maxStamps}
                                    />
                                    <CustomerWhatsApp customer={c} />
                                </li>
                            ))}
                        </ul>

                        <Pagination
                            paginator={customers}
                            only={["customers", "search"]}
                        />
                    </>
                )}
            </Panel>
        </OwnerLayout>
    );
}
