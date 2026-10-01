import { Link, router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    LuBanknote,
    LuBoxes,
    LuPackage,
    LuPackageCheck,
    LuPlus,
    LuTag,
    LuX,
    LuTruck,
} from "react-icons/lu";
import {
    KpiTile,
    RangeTabs,
    StackedLines,
} from "@/Components/Dashboard/Charts";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import DataTable from "@/Components/Dashboard/DataTable";
import ManualOrderForm from "@/Components/Dashboard/ManualOrderForm";
import OrderEditDialog from "@/Components/Dashboard/OrderEditDialog";
import OrderPaymentActions from "@/Components/Dashboard/OrderPaymentActions";
import OrderStageEditor from "@/Components/Dashboard/OrderStageEditor";
import {
    EmptyState,
    Panel,
    primaryButton,
    secondaryButton,
} from "@/Components/Dashboard/Ui";
import { formatNumber, SERIES } from "@/lib/charts";
import { formatPence, formatPenceShort } from "@/lib/money";

/* ---------- Status ---------- */

/**
 * Where an order is: its fulfilment stage once paid; before that "awaiting"
 * (arranged with the shop, waiting for its transfer) or "checkout" (a
 * Stripe checkout the owner didn't finish); or "cancelled".
 */
const stage = (order) =>
    order.status === "cancelled"
        ? "cancelled"
        : order.awaiting_payment
          ? "awaiting"
          : (order.stage ?? "checkout");

const STAGES = {
    received: {
        label: "Order received",
        tone: "bg-amber-500/10 text-amber-700",
        dot: "bg-amber-500",
        order: 0,
    },
    processing: {
        label: "Processing",
        tone: "bg-sky-500/10 text-sky-700",
        dot: "bg-sky-500",
        order: 1,
    },
    dispatched: {
        label: "Dispatched",
        tone: "bg-violet-500/10 text-violet-700",
        dot: "bg-violet-500",
        order: 2,
    },
    delivered: {
        label: "Delivered",
        tone: "bg-emerald-500/10 text-emerald-700",
        dot: "bg-emerald-500",
        order: 3,
    },
    awaiting: {
        label: "Awaiting payment",
        tone: "bg-orange-500/10 text-orange-700",
        dot: "bg-orange-500",
        order: 4,
    },
    checkout: {
        label: "Checkout not finished",
        tone: "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border",
        dot: "bg-slate-400",
        order: 5,
    },
    cancelled: {
        label: "Cancelled",
        tone: "bg-brand-bg text-brand-muted line-through ring-1 ring-inset ring-brand-border",
        dot: "bg-slate-300",
        order: 6,
    },
};

/** Date the order reached its current stage. */
const stageDate = (order) =>
    order.steps.find((s) => s.key === order.stage)?.date ?? null;

function StageBadge({ value }) {
    const s = STAGES[value];

    return (
        <span
            className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${s.tone}`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
            {s.label}
        </span>
    );
}

function StageChips({ value, onChange, counts }) {
    const chips = [
        ["all", "All"],
        ["to_do", "To do"],
        ["dispatched", "Dispatched"],
        ["awaiting", "Awaiting payment"],
        ["delivered", "Delivered"],
    ];

    return (
        <div
            className="no-scrollbar -mx-1 flex gap-1.5 overflow-x-auto px-1"
            role="group"
            aria-label="Filter by status"
        >
            {chips.map(([key, label]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onChange(key)}
                    aria-pressed={value === key}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                        value === key
                            ? "bg-brand-deep text-white"
                            : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                    }`}
                >
                    {STAGES[key] && (
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${STAGES[key].dot}`}
                        />
                    )}
                    {label}
                    <span
                        className={`tabular-nums ${value === key ? "text-white/70" : "text-brand-muted/80"}`}
                    >
                        {counts[key]}
                    </span>
                </button>
            ))}
        </div>
    );
}

/* ---------- Columns ---------- */

/** Grid columns; `products` feeds each row's Edit dialog. */
const buildColumns = (products) => [
    {
        id: "order",
        accessorFn: (o) => `#${o.id} ${o.created_label}`,
        header: "Order",
        sortingFn: (a, b) => a.original.id - b.original.id,
        sortDescFirst: true,
        enableHiding: false,
        meta: { label: "Order", csv: (o) => `#${o.id} (${o.created_label})` },
        cell: ({ row: { original: o } }) => (
            <div className="whitespace-nowrap">
                <p className="font-semibold tabular-nums text-brand-text">
                    #{o.id}
                </p>
                <p className="text-xs text-brand-muted">{o.created_label}</p>
            </div>
        ),
    },
    {
        id: "shop",
        accessorFn: (o) => o.shop_name ?? "",
        header: "Shop",
        meta: { label: "Shop" },
        cell: ({ row: { original: o } }) => (
            <Link
                href={`/admin/shops/${o.shop_id}/settings`}
                className="block max-w-56 truncate font-medium text-brand-text hover:text-brand-accent"
            >
                {o.shop_name}
            </Link>
        ),
    },
    {
        id: "stage",
        accessorFn: (o) => stage(o),
        header: "Status",
        enableGlobalFilter: false,
        sortingFn: (a, b) =>
            STAGES[stage(a.original)].order - STAGES[stage(b.original)].order,
        meta: {
            label: "Status",
            csv: (o) =>
                [STAGES[stage(o)].label, stageDate(o), o.courier, o.tracking_number]
                    .filter(Boolean)
                    .join(" · "),
        },
        cell: ({ row: { original: o } }) => (
            <div>
                <StageBadge value={stage(o)} />
                {stageDate(o) && (
                    <p className="mt-0.5 text-xs text-brand-muted">
                        {stageDate(o)}
                    </p>
                )}
                {o.tracking_number && (
                    <p
                        className="max-w-40 truncate text-xs text-brand-muted"
                        title={`${o.courier ?? ""} ${o.tracking_number}`}
                    >
                        {o.courier ? `${o.courier} · ` : ""}
                        {o.tracking_number}
                    </p>
                )}
            </div>
        ),
    },
    {
        id: "product",
        accessorFn: (o) => `${o.quantity} × ${o.product_name}`,
        header: "Product",
        meta: { label: "Product" },
        cell: ({ getValue }) => (
            <span className="block max-w-56 truncate text-brand-text">
                {getValue()}
            </span>
        ),
    },
    {
        id: "total",
        accessorKey: "total_pence",
        header: "Paid",
        enableGlobalFilter: false,
        sortDescFirst: true,
        meta: {
            label: "Paid",
            align: "right",
            csv: (o) => (o.total_pence / 100).toFixed(2),
        },
        cell: ({ row: { original: o } }) => (
            <div className="whitespace-nowrap">
                <p className="tabular-nums text-brand-text">
                    {o.total_pence === 0 && o.list_total_pence > 0
                        ? "Free"
                        : formatPence(o.total_pence)}
                    {o.list_total_pence > o.total_pence && o.total_pence > 0 && (
                        <span className="ml-1 text-xs text-emerald-700">
                            {formatPence(o.list_total_pence - o.total_pence)} off
                        </span>
                    )}
                </p>
                <p className="text-xs text-brand-muted">
                    {o.payment_label}
                    {o.awaiting_payment && ` · ${o.reference}`}
                </p>
            </div>
        ),
    },
    {
        id: "address",
        accessorFn: (o) => o.delivery_address ?? "",
        header: "Deliver to",
        enableSorting: false,
        meta: { label: "Deliver to" },
        cell: ({ row: { original: o } }) =>
            o.delivery_address ? (
                <p
                    className="max-w-64 truncate text-brand-text"
                    title={o.delivery_address}
                >
                    {o.delivery_address}
                </p>
            ) : (
                <span className="text-xs text-red-700">No address</span>
            ),
    },
    {
        id: "placed_by",
        accessorFn: (o) => o.placed_by ?? "",
        header: "Placed by",
        meta: { label: "Placed by", csv: (o) => o.placed_by ?? "" },
        cell: ({ row: { original: o } }) => (
            <div className="max-w-48">
                <p className="truncate text-brand-text">
                    {o.placed_by ?? "–"}
                </p>
                {o.note && (
                    <p
                        className="truncate text-xs text-brand-muted"
                        title={o.note}
                    >
                        {o.note}
                    </p>
                )}
            </div>
        ),
    },
    {
        id: "actions",
        header: () => <span className="sr-only">Actions</span>,
        enableSorting: false,
        enableHiding: false,
        enableGlobalFilter: false,
        meta: { csv: false, stickyRight: true },
        cell: ({ row: { original: o } }) => (
            <div className="flex justify-end gap-1.5">
                <OrderPaymentActions order={o} compact />
                <OrderEditDialog
                    order={o}
                    products={products}
                    buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                />
                <OrderStageEditor
                    order={o}
                    buttonClassName={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                />
            </div>
        ),
    },
];

/* ---------- Page ---------- */

/** Orders, money collected and items sold in the period, each vs the period before. */
function Insights({ insights, days }) {
    const { current, previous } = insights;
    const before = (text) => `${text} the ${days} days before`;
    const average = current.orders
        ? formatPence(Math.round(current.collected_pence / current.orders))
        : "–";

    return (
        // 2 × 2; on wide screens the rows stretch to the chart's height beside it.
        <div className="grid grid-cols-2 gap-3 xl:h-full xl:grid-rows-2">
            <KpiTile
                icon={LuPackageCheck}
                label="Paid orders"
                value={formatNumber(current.orders)}
                current={current.orders}
                previous={previous.orders}
                note={before(`vs ${formatNumber(previous.orders)}`)}
            />
            <KpiTile
                icon={LuBanknote}
                label="Collected"
                value={formatPence(current.collected_pence)}
                current={current.collected_pence}
                previous={previous.collected_pence}
                note={`${formatPence(current.online_pence)} online · ${formatPence(current.manual_pence)} by hand`}
            />
            <KpiTile
                icon={LuBoxes}
                label="Items sold"
                value={formatNumber(current.units)}
                current={current.units}
                previous={previous.units}
                note={`${average} average order`}
            />
            <KpiTile
                icon={insights.to_do > 0 ? LuPackage : LuTruck}
                label="To post now"
                value={formatNumber(insights.to_do)}
                note={`${formatNumber(insights.on_the_way)} on the way · any date`}
            />
        </div>
    );
}

/** Money collected and paid orders per day: two bands, own scale each, one shared day axis. */
function SalesChart({ sales, days, total }) {
    return (
        <Panel
            title="Sales"
            description={`${formatPence(total)} in the last ${days} days, by day paid`}
            bodyClassName="px-5 pb-4 pt-3"
        >
            <StackedLines
                data={sales}
                caption="Sales per day"
                lines={[
                    {
                        key: "collected",
                        label: "Collected per day",
                        color: SERIES.primary,
                        height: 100,
                        area: true,
                        format: formatPence,
                        tickFormat: formatPenceShort,
                    },
                    {
                        key: "orders",
                        label: "Paid orders per day",
                        color: SERIES.secondary,
                        height: 50,
                    },
                ]}
            />
        </Panel>
    );
}

/** "New order": an order arranged with a shop (phone, visit, promotion). */
function NewOrderDialog({ shops, products, bankDetailsSet, onClose }) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4"
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label="New order"
                onClick={(e) => e.stopPropagation()}
                className="max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card p-6 sm:max-w-xl sm:rounded-2xl"
            >
                <div className="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h2 className="font-heading text-lg font-semibold text-brand-text">
                            New order
                        </h2>
                        <p className="mt-0.5 text-sm text-brand-muted">
                            Arranged with the shop - at the normal price, an
                            offer, or free.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className="-m-1 rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg"
                    >
                        <LuX className="h-4 w-4" />
                    </button>
                </div>
                <ManualOrderForm
                    shops={shops}
                    products={products}
                    bankDetailsSet={bankDetailsSet}
                    onDone={onClose}
                />
            </div>
        </div>
    );
}

export default function Index({
    orders,
    insights,
    sales,
    days,
    ranges,
    shops,
    products,
    bankDetailsSet,
}) {
    const [filter, setFilter] = useState("all");
    const [creating, setCreating] = useState(false);
    const columns = useMemo(() => buildColumns(products), [products]);
    const [loading, setLoading] = useState(false);

    // Same per-period switch as the admin dashboard; ?days= in the URL.
    function changeRange(range) {
        router.reload({
            data: { days: range },
            only: ["orders", "insights", "sales", "days"],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    const counts = useMemo(() => {
        const c = { all: orders.length, to_do: 0, dispatched: 0, delivered: 0, awaiting: 0 };
        orders.forEach((o) => {
            const st = stage(o);
            const key = st === "received" || st === "processing" ? "to_do" : st;
            if (key in c) c[key] += 1;
        });

        return c;
    }, [orders]);

    const rows = useMemo(
        () =>
            filter === "all"
                ? orders
                : filter === "to_do"
                  ? orders.filter((o) => ["received", "processing"].includes(stage(o)))
                  : orders.filter((o) => stage(o) === filter),
        [orders, filter],
    );

    return (
        <AdminLayout
            title="Orders"
            description={`Last ${days} days, plus anything not delivered yet.`}
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    <RangeTabs
                        ranges={ranges}
                        value={days}
                        busy={loading}
                        onChange={changeRange}
                    />
                    <Link href="/admin/products" className={secondaryButton}>
                        <LuTag className="h-4 w-4" /> Products
                    </Link>
                    {products.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setCreating(true)}
                            className={primaryButton}
                        >
                            <LuPlus className="h-4 w-4" /> New order
                        </button>
                    )}
                </div>
            }
        >
            {/* Numbers left, chart right on wide screens; stacked below that. */}
            <div
                className={`mb-4 grid grid-cols-1 gap-4 transition-opacity xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] ${loading ? "opacity-60" : ""}`}
            >
                <Insights insights={insights} days={days} />
                <SalesChart
                    sales={sales}
                    days={days}
                    total={insights.current.collected_pence}
                />
            </div>

            <DataTable
                data={rows}
                columns={columns}
                initialSorting={[{ id: "order", desc: true }]}
                searchPlaceholder="Search shop, product or address"
                storageKey="admin.orders.table"
                exportName="tada-tap-orders"
                filteredExternally={filter !== "all"}
                onClearAll={() => setFilter("all")}
                toolbar={
                    <StageChips
                        value={filter}
                        onChange={setFilter}
                        counts={counts}
                    />
                }
                empty={
                    <EmptyState
                        icon={orders.length ? LuPackageCheck : LuPackage}
                        title={orders.length ? "Nothing here" : "No orders yet"}
                    >
                        {orders.length
                            ? "No orders match this filter."
                            : "Orders appear here when an owner pays online, or when you record one on a shop's page."}
                    </EmptyState>
                }
            />
            {creating && (
                <NewOrderDialog
                    shops={shops}
                    products={products}
                    bankDetailsSet={bankDetailsSet}
                    onClose={() => setCreating(false)}
                />
            )}
        </AdminLayout>
    );
}
